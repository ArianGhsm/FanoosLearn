#!/usr/bin/env python3
"""48-hour self-resuming Claude Opus manager, running only in isolated fanoosworker.

No production writes, no privileged operations, no PDF copies. Host-controlled
operator bridge, if installed separately, owns audited DB source-only changes.
"""
from __future__ import annotations
import argparse
import datetime as dt
import fcntl
import json
import os
from pathlib import Path
import signal
import subprocess
import sys
import tempfile
import time

from runner import scan_quota

UTC=dt.timezone.utc
CLI='/mnt/workspace/home/bin/claude'
WHITELIST={'PATH','HOME','LANG','LC_ALL','TZ','USER','LOGNAME','CLAUDE_CONFIG_DIR'}
DEFAULT_CMD=[CLI,'--print','--model','opus','--effort','medium',
 '--permission-mode','auto','--tools','Bash,Read,Write,Edit,Glob,Grep',
 '--add-dir','/srv/fanoos/shared/research','--add-dir','/srv/fanoos/current',
 '--add-dir','/mnt/workspace/FanoosLearn-classifier-v3','--output-format','json']

def utc(s):
 d=dt.datetime.fromisoformat(s.replace('Z','+00:00'))
 if d.tzinfo is None: raise ValueError('Must supply UTC timezone')
 return d.astimezone(UTC)

def save(path,obj):
 fd,t=tempfile.mkstemp(dir=path.parent,prefix='.lead-')
 try:
  os.fchmod(fd,0o600)
  with os.fdopen(fd,'w',encoding='utf-8') as f:
   json.dump(obj,f,ensure_ascii=False,indent=1,sort_keys=True)
   f.flush();os.fsync(f.fileno())
  os.replace(t,path)
 finally:
  if os.path.exists(t):os.unlink(t)

def run(a):
 if os.geteuid()==0 or os.getuid()==982:
  raise RuntimeError('Lead must run unprivileged and never as source publisher')
 deadline=utc(a.deadline)
 not_before=utc(a.not_before)
 home=a.state_dir.resolve()
 home.mkdir(parents=True,exist_ok=True,mode=0o700)
 with open(home/'lead.lock','a') as lock:
  try: fcntl.flock(lock,fcntl.LOCK_EX|fcntl.LOCK_NB)
  except BlockingIOError:print('LEAD_ALREADY_RUNNING');return 0
  statefile=home/'state.json'
  state=json.load(open(statefile)) if statefile.exists() else {}
  now=dt.datetime.now(UTC)
  due=utc(state['next_allowed_utc']) if state.get('next_allowed_utc') else not_before
  if now<not_before or now<due or now>=deadline:
   print(json.dumps({'status':'skipped','reason':'quota_backoff_or_deadline',
         'now':now.isoformat(),'next':max(due,not_before).isoformat(),
         'deadline':deadline.isoformat()}))
   return 0
  space=os.statvfs(home);free=space.f_bavail*space.f_frsize
  if free<3*1024**3:
   state['last_status']='disk_guard';save(statefile,state);print('LEAD_DISK_GUARD');return 0
  if not a.mission.is_file():
   raise FileNotFoundError('private mission document unavailable')
  mission=a.mission.read_text(encoding='utf-8')
  # Each session gets fresh source-of-truth from Git + durable private handoff.
  prompt=(mission+'\n\nEXECUTION DATE UTC: '+now.isoformat()+
   '\nHARD DEADLINE UTC: '+deadline.isoformat()+
   '\nThis is a new isolated Opus session of a recurring server-side manager. '
   'Read the repository and private handoff before taking actions. '
   'Continue tasks, persist checkpoints before exit and clean temporary files. '
   'Do not spin off background detached model calls or edit systemd units. '
   'Provide concise final outcomes. Use existing repository tools only.')
  fd,t=tempfile.mkstemp(prefix='.opus-lead-',suffix='.json',dir=home)
  os.fchmod(fd,0o600)
  t0=time.monotonic();rc=0;killed=False
  remaining=int((deadline-now).total_seconds())
  with os.fdopen(fd,'wb') as out:
   try:
    proc=subprocess.Popen(DEFAULT_CMD,stdin=subprocess.PIPE,stdout=out,
      stderr=subprocess.STDOUT,cwd=str(a.repo),
      env={k:v for k,v in os.environ.items() if k in WHITELIST},
      start_new_session=True,preexec_fn=lambda:os.nice(10))
    try:proc.communicate(input=prompt.encode('utf-8'),timeout=min(a.timeout,remaining))
    except subprocess.TimeoutExpired:
     killed=True;os.killpg(proc.pid,signal.SIGTERM)
     try:proc.wait(20)
     except subprocess.TimeoutExpired:
      os.killpg(proc.pid,signal.SIGKILL);proc.wait()
    rc=proc.returncode
   except BaseException:
    killed=True
    raise
  data={}
  try:
   with open(t,'r',encoding='utf-8') as f:data=json.load(f)
  except (OSError,ValueError):pass
  tail=Path(t).read_bytes()[-8192:].decode('utf-8','replace')
  error_status=data.get('api_error_status')
  quota,delay=scan_quota(tail)
  if error_status==429:quota=True
  status=('quota' if quota else 'timeout' if killed else 'ok'
          if rc==0 and not data.get('is_error') else 'failed')
  next_delay=(delay or 3600) if quota else (1800 if status=='timeout' else
             min(7200,600*(int(state.get('consecutive_errors',0))+1)) if status=='failed'
             else 1800)
  next_time=min(deadline,dt.datetime.now(UTC)+dt.timedelta(seconds=next_delay))
  # Keep only limited history for user privacy and quota management.
  state.update({'updated_at_utc':dt.datetime.now(UTC).isoformat(),
   'last_status':status,'last_rc':rc,'last_seconds':round(time.monotonic()-t0,2),
   'last_killed':killed,'next_allowed_utc':next_time.isoformat(),
   'last_session_id':data.get('session_id'),
   'last_api_error_status':error_status,'deadline_utc':deadline.isoformat(),
   'runs':int(state.get('runs',0))+1,
   'successful_runs':int(state.get('successful_runs',0))+(status=='ok'),
   'consecutive_errors':0 if status=='ok' else int(state.get('consecutive_errors',0))+1,
   'model_cost_usd_list_estimate':round(float(state.get('model_cost_usd_list_estimate',0))
     +float(data.get('total_cost_usd') or 0),4)})
  save(statefile,state)
  log=home/'history.jsonl'
  with log.open('a',encoding='utf-8') as f:
   os.chmod(log,0o600)
   f.write(json.dumps({k:state[k] for k in ('updated_at_utc','last_status',
    'last_rc','last_seconds','last_killed','next_allowed_utc','last_api_error_status',
    'runs','successful_runs','model_cost_usd_list_estimate')})+'\n')
  # Raw model output is private and retained only for last 3 sessions.
  dest=home/('session-'+str(state['runs']).zfill(4)+'.json')
  os.replace(t,dest)
  sessions=sorted(home.glob('session-*.json'))
  for old in sessions[:-3]:
   old.unlink()
  print(json.dumps({'status':status,'next_allowed_utc':next_time.isoformat(),
   'runs':state['runs'],'list_cost_usd_est':state['model_cost_usd_list_estimate'],
   'session_id':data.get('session_id')}))
  return 0

if __name__=='__main__':
 ap=argparse.ArgumentParser()
 ap.add_argument('--mission',type=Path,required=True)
 ap.add_argument('--state-dir',type=Path,required=True)
 ap.add_argument('--repo',type=Path,default=Path('/mnt/workspace/FanoosLearn-classifier-v3'))
 ap.add_argument('--not-before',default='2026-11-10T01:12:00Z')
 ap.add_argument('--deadline',default='2026-11-11T22:06:20Z')
 ap.add_argument('--timeout',type=int,default=960)
 a=ap.parse_args()
 if a.timeout<120 or a.timeout>1500:ap.error('timeout must be 120..1500')
 sys.exit(run(a))
