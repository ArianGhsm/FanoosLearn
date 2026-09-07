from __future__ import annotations
import os, secrets, sqlite3, time
from pathlib import Path

class LocalState:
    def __init__(self,path: str|Path):
        self.path=Path(path); self.path.parent.mkdir(parents=True,exist_ok=True)
        try: os.chmod(self.path.parent,0o700)
        except OSError: pass
        self.db=sqlite3.connect(self.path); self.db.row_factory=sqlite3.Row
        self.db.executescript('''PRAGMA journal_mode=WAL; PRAGMA synchronous=FULL;
        CREATE TABLE IF NOT EXISTS kv(k TEXT PRIMARY KEY,v TEXT NOT NULL);
        CREATE TABLE IF NOT EXISTS confirmations(ref TEXT PRIMARY KEY,subject TEXT NOT NULL,target_key TEXT NOT NULL,idempotency_key TEXT NOT NULL,expires_at INTEGER NOT NULL,used_at INTEGER);
        CREATE TABLE IF NOT EXISTS deployments(subject TEXT NOT NULL,request_id TEXT NOT NULL,created_at INTEGER NOT NULL,PRIMARY KEY(subject,request_id));
        CREATE TABLE IF NOT EXISTS sent_deliveries(delivery_id TEXT PRIMARY KEY,provider_ref TEXT NOT NULL,created_at INTEGER NOT NULL);
        CREATE TABLE IF NOT EXISTS file_cache(k TEXT PRIMARY KEY,file_id TEXT NOT NULL,updated_at INTEGER NOT NULL);'''); self.db.commit()
        try: os.chmod(self.path,0o600)
        except OSError: pass
    def close(self): self.db.close()
    def get_offset(self,platform: str)->int:
        r=self.db.execute('SELECT v FROM kv WHERE k=?',(f'offset:{platform}',)).fetchone(); return int(r['v']) if r else 0
    def set_offset(self,platform: str,value:int):
        self.db.execute('INSERT INTO kv(k,v) VALUES(?,?) ON CONFLICT(k) DO UPDATE SET v=excluded.v',(f'offset:{platform}',str(int(value)))); self.db.commit()
    def create_confirmation(self,subject:str,target_key:str,ttl:int=120,now:int|None=None):
        now=int(now or time.time()); ref=secrets.token_hex(8); idem='bot-update-'+secrets.token_hex(12)
        self.db.execute('INSERT INTO confirmations VALUES(?,?,?,?,?,NULL)',(ref,str(subject),target_key,idem,now+ttl)); self.db.commit(); return {'ref':ref,'idempotency_key':idem,'expires_at':now+ttl}
    def confirmation(self,ref:str,subject:str,now:int|None=None):
        now=int(now or time.time()); r=self.db.execute('SELECT * FROM confirmations WHERE ref=? AND subject=?',(ref,str(subject))).fetchone()
        if not r or r['used_at'] is not None or int(r['expires_at'])<now: return None
        return dict(r)
    def cancel_confirmation(self,ref:str,subject:str):
        self.db.execute('UPDATE confirmations SET expires_at=0 WHERE ref=? AND subject=? AND used_at IS NULL',(ref,str(subject))); self.db.commit()
    def complete_confirmation(self,ref:str,subject:str):
        self.db.execute('UPDATE confirmations SET used_at=? WHERE ref=? AND subject=? AND used_at IS NULL',(int(time.time()),ref,str(subject))); self.db.commit()
    def record_deployment(self,subject:str,request_id:str):
        self.db.execute('INSERT OR IGNORE INTO deployments VALUES(?,?,?)',(str(subject),request_id,int(time.time()))); self.db.commit()
    def latest_deployment(self,subject:str):
        r=self.db.execute('SELECT request_id FROM deployments WHERE subject=? ORDER BY created_at DESC LIMIT 1',(str(subject),)).fetchone(); return r['request_id'] if r else None
    def remember_delivery(self,delivery_id:str,provider_ref:str):
        self.db.execute('INSERT OR REPLACE INTO sent_deliveries VALUES(?,?,?)',(delivery_id,provider_ref,int(time.time()))); self.db.commit()
    def sent_delivery(self,delivery_id:str):
        r=self.db.execute('SELECT provider_ref FROM sent_deliveries WHERE delivery_id=?',(delivery_id,)).fetchone(); return r['provider_ref'] if r else None
    def cache_file(self,key:str,file_id:str):
        self.db.execute('INSERT OR REPLACE INTO file_cache VALUES(?,?,?)',(key,file_id,int(time.time()))); self.db.commit()
    def cached_file(self,key:str):
        r=self.db.execute('SELECT file_id FROM file_cache WHERE k=?',(key,)).fetchone(); return r['file_id'] if r else None
    def prune(self,max_age:int=7*86400):
        cutoff=int(time.time())-max_age; self.db.execute('DELETE FROM sent_deliveries WHERE created_at<?',(cutoff,)); self.db.execute('DELETE FROM file_cache WHERE updated_at<?',(cutoff,)); self.db.commit()
