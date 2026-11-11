#!/usr/bin/env python3
"""Root-owned wrapper for independent Opus QA; model executes as sandbox worker.

The model never touches root-owned output or operator credentials. Its exact
structured result is captured by the host and SHA-pinned in protected state.
Root source-only publisher may only trust such host-owned QA attestations.
"""
from __future__ import annotations
import argparse
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path
import pwd
import re
import signal
import shutil
import subprocess
import sys
import tempfile
import time

UTC=dt.timezone.utc
BASE=Path('/srv/fanoos/shared/research/classification/v3-runner')
STATE=Path('/var/lib/fanoos-v3-operator')
QA_SESSIONS=STATE/'qa-sessions'
QA_ATTESTATIONS=STATE/'qa-attestations'
QA_FORMAT='fanoos.classifier-v3.independent-opus-qa.v1'
HEXID=re.compile(r'^[a-f0-9]{16}$')
UUID_RE=re.compile(r'^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$')
RESET_RE=re.compile(r'resets?\s+(\d{1,2}):(\d{2})\s*(am|pm)\s*\(UTC\)',re.I)
RATE_RE=re.compile(r'rate[_ -]?limit|usage[_ -]?limit|session[_ -]?limit|\b429\b',re.I)
PROVIDER_RESET=dt.datetime(2026,11,10,1,10,tzinfo=UTC)
try:WORKER=pwd.getpwnam('fanoosworker')
except KeyError:WORKER=None  # host-only account; any privileged use then fails closed
MODEL='/mnt/workspace/home/bin/claude'
SCHEMA={'type':'object','additionalProperties':False,'properties':{
 'batch_id':{'type':'string'},'verdicts':{'type':'array','items':{
  'type':'object','additionalProperties':False,
  'properties':{'question_id':{'type':'string'},'approved':{'type':'boolean'},
   'answer_fully_supported':{'type':'boolean'},
   'all_options_and_official_key_checked':{'type':'boolean'},
   'no_material_conflict':{'type':'boolean'},'edition_policy_checked':{'type':'boolean'},
   'physical_page_chapter_sha_checked':{'type':'boolean'},'reason':{'type':'string'}},
  'required':['question_id','approved','answer_fully_supported',
   'all_options_and_official_key_checked','no_material_conflict',
   'edition_policy_checked','physical_page_chapter_sha_checked','reason']}}},
 'required':['batch_id','verdicts']}

class Unsafe(Exception):pass

def utc(s):
 d=dt.datetime.fromisoformat(s.replace('Z','+00:00'))
 if not d.tzinfo:raise Unsafe('must use UTC')
 return d.astimezone(UTC)

def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()

def load(p):
 if p.is_symlink() or not p.is_file():raise Unsafe('protected source unavailable or symlink')
 return json.loads(p.read_text('utf-8'))

def root_dir(p):
 p.mkdir(mode=0o700,parents=True,exist_ok=True)
 if p.is_symlink() or p.stat().st_uid!=0 or p.stat().st_mode & 0o022:
  raise Unsafe('QA state not root protected')
 os.chmod(p,0o700)

def atom(path,obj):
 b=(json.dumps(obj,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode('utf-8')
 fd,tmp=tempfile.mkstemp(prefix='.trustedqa-',dir=path.parent)
 try:
  os.fchmod(fd,0o600)
  with os.fdopen(fd,'wb') as f:f.write(b);f.flush();os.fsync(f.fileno())
  os.replace(tmp,path)
 except:
  try:os.unlink(tmp)
  except FileNotFoundError:pass
  raise

def quota_delay(out):
 if not isinstance(out,dict):return False,3600
 raw=json.dumps({k:out.get(k) for k in ('api_error_status','api_error','result')},ensure_ascii=False)
 limited=out.get('api_error_status')==429 or bool(RATE_RE.search(raw))
 if not limited:return False,0
 m=RESET_RE.search(raw)
 if not m:return True,3600
 h=int(m.group(1));minute=int(m.group(2));suffix=m.group(3).lower()
 if h<1 or h>12 or minute>59:return True,3600
 hour=h%12+(12 if suffix=='pm' else 0)
 now=dt.datetime.now(UTC)
 target=now.replace(hour=hour,minute=minute,second=0,microsecond=0)
 if target<=now:target+=dt.timedelta(days=1)
 return True,min(48*3600,max(60,int((target-now).total_seconds())+120))

def safe_input(batch):
 if not HEXID.fullmatch(batch):raise Unsafe('invalid batch id')
 request_path=BASE/'operator-requests'/f'{batch}.json'
 request=load(request_path)
 case=BASE/'cases'/f'v3-{batch}'
 if request.get('format')!='fanoos.classifier-v3.operator-request.v1' or request.get('batch_id')!=batch:
  raise Unsafe('research request mismatched')
 if request.get('case_dir')!=str(case) or not case.is_dir() or case.is_symlink():
  raise Unsafe('research case invalid path')
 draft_path=case/'decisions.draft.json'
 d=load(draft_path)
 proposed=[q for q in d.get('decisions',[]) if q.get('decision')=='proposed']
 if len(proposed)>40 or len(proposed)!=len({q.get('question_id') for q in proposed}):
  raise Unsafe('research proposal count invalid')
 for q in proposed:
  if not UUID_RE.fullmatch(str(q.get('question_id',''))):
   raise Unsafe('research UUID invalid')
  if q.get('key_class')!='final':
   raise Unsafe('preliminary/voided answer cannot be auto-approved')
 research=load(case/'session.out')
 if not research.get('session_id'):raise Unsafe('research model session unavailable')
 return request_path,request,case,draft_path,proposed,research['session_id']

def strict_qa(data,batch,proposed):
 if data.get('is_error') or data.get('api_error_status'):
  raise Unsafe('independent QA model failed')
 if not any('opus' in x.lower() for x in (data.get('modelUsage') or {})):
  raise Unsafe('review was not Opus')
 result=data.get('structured_output')
 if isinstance(result,str):
  try:result=json.loads(result)
  except ValueError:raise Unsafe('unparseable structured QA result')
 if not isinstance(result,dict) or result.get('batch_id')!=batch:
  raise Unsafe('invalid QA structured output')
 rows=result.get('verdicts')
 if not isinstance(rows,list) or len(rows)!=len(proposed):
  raise Unsafe('not all proposed sources independently reviewed')
 by={q['question_id']:q for q in proposed}
 verdicts={}
 for v in rows:
  if not isinstance(v,dict) or v.get('question_id') not in by or v['question_id'] in verdicts:
   raise Unsafe('unknown or duplicate reviewer question')
  if not isinstance(v.get('reason'),str) or len(v['reason'])<22:
   raise Unsafe('reviewer did not explain decision')
  flags=('answer_fully_supported','all_options_and_official_key_checked',
      'no_material_conflict','edition_policy_checked','physical_page_chapter_sha_checked')
  if not all(isinstance(v.get(k),bool) for k in flags) or not isinstance(v.get('approved'),bool):
   raise Unsafe('reviewer flags missing')
  verdicts[v['question_id']]=v
 approved=[]
 for q in proposed:
  v=verdicts[q['question_id']]
  if not (v['approved'] and all(v[k] for k in flags)):
   continue
  if not isinstance(q.get('confidence'),(int,float)) or q['confidence']<0.85:
   continue
  # For multi-key questions require clinical reconciliation outside unattended flow.
  study=load(BASE/'cases'/f'v3-{batch}'/f"study-{q['year']}.json")
  original=next((x for x in study['questions'] if x['number']==q['number']),None)
  if not original:raise Unsafe('QA original study number missing')
  key=original.get('answer') or {}
  if key.get('status')!='final' or key.get('also_correct'):
   continue
  approved.append(q)
 return result['verdicts'],approved

def model_in_worker(prompt,output,timeout,budget):
 def drop():
  os.initgroups(WORKER.pw_name,WORKER.pw_gid)
  os.setgid(WORKER.pw_gid)
  os.setuid(WORKER.pw_uid)
 cmd=['/usr/local/sbin/fanoosworker-shell',MODEL,'--print','--model','opus',
      '--effort','medium','--permission-mode','auto','--tools','Bash,Read',
      '--add-dir','/srv/fanoos/shared/research','--add-dir','/srv/fanoos/current',
      '--output-format','json','--max-budget-usd',str(budget),
      '--json-schema',json.dumps(SCHEMA,separators=(',',':'))]
 fd=os.open(output,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
 start=time.monotonic()
 with os.fdopen(fd,'wb') as f:
  p=subprocess.Popen(cmd,stdin=subprocess.PIPE,stdout=f,stderr=subprocess.STDOUT,
       cwd='/var/lib/fanoos-worker/workspace/FanoosLearn-classifier-v3',
       env={'PATH':'/usr/local/bin:/usr/bin:/bin','LANG':'C.UTF-8'},
       start_new_session=True,preexec_fn=drop)
  try:p.communicate(prompt.encode('utf-8'),timeout=timeout)
  except subprocess.TimeoutExpired:
   os.killpg(p.pid,signal.SIGTERM)
   try:p.wait(20)
   except subprocess.TimeoutExpired:
    os.killpg(p.pid,signal.SIGKILL);p.wait()
  return p.returncode,round(time.monotonic()-start,2)

def process(batch,a,state):
 reqp,req,case,draft,proposals,research_id=safe_input(batch)
 qa_file=BASE/'qa-approved'/f'{batch}.json'
 root_rec=QA_ATTESTATIONS/f'{batch}.json'
 output=QA_SESSIONS/f'{batch}.json'
 if root_rec.exists():
  print(json.dumps({'status':'previously_attested','id':batch}))
  return True
 if qa_file.exists():
  raise Unsafe('untrusted worker-created QA exists without host attestation')
 req_sha_before=sha(reqp)
 draft_sha_before=sha(draft)
 if not proposals:
  v=[]
  approved=[]
  data={'session_id':None,'modelUsage':{},'is_error':False}
  output_sha=None
 else:
  if not output.exists():
   prompt=(f"Act as an INDEPENDENT new Claude Opus scientific reviewer, separate from "
     f"the research session {research_id}. Inspect the research draft {draft}, "
     f"request {reqp}, original source-free studies in {case}/study-YYYY.json, "
     f"and official answer keys/options/figures. For each proposed question_id "
     f"read the verified edition original PDF using "
     f"/srv/fanoos/current/scripts/references/verified_reference_pdf.py "
     f"ONE PHYSICAL PAGE in RAM at a time, chapter map SHA, exact short quote, "
     f"FULL official main answer and alternatives. "
     f"Do not claim a negative answer from a nonexhaustive list or accept "
     f"one component of a two-part response. If book conflicts with the official "
     f"main key, REJECT even if also_correct choice is supported. "
     f"Check newest officially eligible residency edition first and only "
     f"older fallback with explicitly reviewed newer edition; no board-only "
     f"editions in residency. Examine answer validity, chapter scope, page and "
     f"SHA. Return STRICT JSON using supplied schema: one verdict per proposed "
     f"question, all flags true only when independently proven, reason >=22 chars. "
     f"False for doubtful cases. NO files modified, no subagents, no external "
     f"production writes, no PDF fulltext persistence or OCR.")
   temp=QA_SESSIONS/f'.{batch}.{os.getpid()}.tmp'
   if temp.exists():temp.unlink()
   try:
    rc,elapsed=model_in_worker(prompt,temp,min(a.timeout,
         int((utc(a.deadline)-dt.datetime.now(UTC)).total_seconds())),a.budget)
    try:data=load(temp)
    except (Unsafe,ValueError):
     data={'is_error':True,'api_error':'unstructured-output'}
    limited,delay=quota_delay(data)
    state['last_model_cost_usd_est']=float(data.get('total_cost_usd') or 0)
    state['total_model_list_usd_est']=round(float(state.get('total_model_list_usd_est',0))+
       state['last_model_cost_usd_est'],4)
    if limited:
     state['next_allowed_utc']=(dt.datetime.now(UTC)+dt.timedelta(seconds=delay)).isoformat()
     print(json.dumps({'status':'provider_usage_limit','batch_id':batch,
           'retry_seconds':delay}))
     return 'quota'
    if rc or data.get('is_error'):
     print(json.dumps({'status':'independent_opus_error','id':batch,'rc':rc,'seconds':elapsed}))
     return False
    os.replace(temp,output)
   finally:
    if temp.exists():temp.unlink()
  data=load(output)
  if data.get('session_id')==research_id or not data.get('session_id'):
   raise Unsafe('scientific QA session not independent')
  try:
   v,approved=strict_qa(data,batch,proposals)
  except (Unsafe,ValueError):
   if output.exists() and not root_rec.exists():output.unlink()
   raise
  output_sha=sha(output)
 if sha(draft)!=draft_sha_before or sha(reqp)!=req_sha_before:
  raise Unsafe('research input mutated during independent QA')
 qa_data={'format':QA_FORMAT,'batch_id':batch,
  'research_draft_sha256':sha(draft),'research_request_sha256':sha(reqp),
  'independent_qa_session_id':data.get('session_id'),
  'independent_model':'claude-opus' if proposals else 'not_needed',
  'qa_output_sha256':output_sha,'verdicts':v,'approved':approved,
  'approved_count':len(approved),'rejected_count':len(proposals)-len(approved),
  'created_at_utc':dt.datetime.now(UTC).isoformat()}
 # Host writes the worker-visible packet, but the authoritative matching
 # SHA attestation lives in root-only state and cannot be altered by worker.
 fd=os.open(qa_file,os.O_CREAT|os.O_EXCL|os.O_WRONLY,0o640)
 os.fchown(fd,0,WORKER.pw_gid)
 os.fchmod(fd,0o640)
 with os.fdopen(fd,'w',encoding='utf-8') as f:
  json.dump(qa_data,f,ensure_ascii=False,indent=2);f.write('\n')
  f.flush();os.fsync(f.fileno())
 att={'batch_id':batch,'qa_packet_sha256':sha(qa_file),
      'model_output_sha256':output_sha,'session_id':data.get('session_id'),
      'draft_sha256':sha(draft),'request_sha256':sha(reqp),
      'approved_count':len(approved),'created_at_utc':dt.datetime.now(UTC).isoformat()}
 atom(root_rec,att)
 print(json.dumps({'status':'host_attested_independent_opus_qa','batch_id':batch,
       'approved':len(approved),'rejected':len(proposals)-len(approved)}))
 return True

def main():
 ap=argparse.ArgumentParser()
 ap.add_argument('--dry-run',action='store_true')
 ap.add_argument('--run',action='store_true')
 ap.add_argument('--not-before',default='2026-11-10T01:12:00Z')
 ap.add_argument('--deadline',default='2026-11-11T22:06:20Z')
 ap.add_argument('--timeout',type=int,default=410)
 ap.add_argument('--budget',type=float,default=2.0)
 a=ap.parse_args()
 if os.geteuid()!=0 or a.run==a.dry_run:ap.error('root-only and choose --dry-run or --run')
 if not 120<=a.timeout<=900 or not 0<a.budget<=5:ap.error('unsafe budget/timebox')
 root_dir(STATE);root_dir(QA_SESSIONS);root_dir(QA_ATTESTATIONS)
 with (STATE/'qa-host.lock').open('a') as lk:
  fcntl.flock(lk,fcntl.LOCK_EX|fcntl.LOCK_NB)
  now=dt.datetime.now(UTC)
  state_file=STATE/'qa_state.json'
  state=load(state_file) if state_file.exists() else {'failed':{}}
  if a.run:
   if now<utc(a.not_before) or now>=utc(a.deadline) or (
     state.get('next_allowed_utc') and now<utc(state['next_allowed_utc'])):
    print(json.dumps({'status':'quota_reset_or_deadline_guard'}))
    return 0
   if shutil.disk_usage(BASE).free<3*1024**3:
    print('QA_DISK_GUARD');return 0
  for path in sorted((BASE/'operator-requests').glob('*.json'),key=lambda p:p.stat().st_mtime):
   batch=path.stem
   if not HEXID.fullmatch(batch) or (BASE/'operator-receipts'/path.name).exists():
    continue
   if state['failed'].get(batch,0)>=3:
    continue
   if (QA_ATTESTATIONS/(batch+'.json')).exists():
    continue
   if a.dry_run:
    print(json.dumps({'status':'host_qa_ready','id':batch}))
    return 0
   try:
    completed=process(batch,a,state)
    if completed is True:state['failed'].pop(batch,None)
    elif completed=='quota':pass
    else:state['failed'][batch]=state['failed'].get(batch,0)+1
   except (Unsafe,KeyError,TypeError,ValueError,subprocess.TimeoutExpired) as e:
    state['failed'][batch]=state['failed'].get(batch,0)+1
    print(json.dumps({'status':'pending_host_opus_qa','id':batch,
                      'error_type':type(e).__name__,'attempt':state['failed'][batch]}))
   finally:
    atom(state_file,state)
   return 0
  print(json.dumps({'status':'no_unreviewed_research_batches'}))
 return 0

if __name__=='__main__':
 sys.exit(main())
