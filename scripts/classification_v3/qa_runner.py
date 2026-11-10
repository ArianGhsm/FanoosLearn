#!/usr/bin/env python3
"""Second, independent Claude Opus scientific QA for V3 research drafts.

This process runs only as fanoosworker and may create a private reviewed
REQUEST. It never audits its own provenance, touches production, or signs an
operator receipt. Host-side fanoosupd independently rechecks all evidence.
"""
from __future__ import annotations
import argparse
import datetime as dt
import fcntl
import hashlib
import json
import os
from pathlib import Path
import re
import signal
import subprocess
import sys
import tempfile
import time
from runner import scan_quota, atomic_write, RECEIPT_FORMAT

UTC=dt.timezone.utc
ID_RE=re.compile(r'^[a-f0-9]{16}$')
QUESTION_ID_RE=re.compile(r'^[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}$')
REVIEW_FORMAT='fanoos.classifier-v3.preliminary-worker-qa.v1'
ROOT=Path('/srv/fanoos/shared/research/classification/v3-runner')
REPO=Path('/mnt/workspace/FanoosLearn-classifier-v3')
MODEL='/mnt/workspace/home/bin/claude'
SCHEMA={
 'type':'object','additionalProperties':False,
 'properties':{
  'batch_id':{'type':'string'},
  'verdicts':{'type':'array','items':{
   'type':'object','additionalProperties':False,'properties':{
    'question_id':{'type':'string'},'approved':{'type':'boolean'},
    'answer_fully_supported':{'type':'boolean'},
    'all_options_and_official_key_checked':{'type':'boolean'},
    'no_material_conflict':{'type':'boolean'},
    'edition_policy_checked':{'type':'boolean'},
    'physical_page_chapter_sha_checked':{'type':'boolean'},
    'reason':{'type':'string'}},
   'required':['question_id','approved','answer_fully_supported',
    'all_options_and_official_key_checked','no_material_conflict',
    'edition_policy_checked','physical_page_chapter_sha_checked','reason']}}
 },
 'required':['batch_id','verdicts']
}
ENV_KEEP=('PATH','HOME','LANG','USER','LOGNAME','CLAUDE_CONFIG_DIR')

def utc(s):
 d=dt.datetime.fromisoformat(s.replace('Z','+00:00'))
 if d.tzinfo is None:raise ValueError('timezone needed')
 return d.astimezone(UTC)

def sha(path):
 return hashlib.sha256(path.read_bytes()).hexdigest()

def safe_request(path):
 assert path.is_file() and not path.is_symlink()
 id_=path.stem
 if not ID_RE.fullmatch(id_):raise ValueError('bad id')
 request=json.loads(path.read_text())
 if request.get('batch_id')!=id_ or request.get('format')!='fanoos.classifier-v3.operator-request.v1':
  raise ValueError('request identity mismatch')
 case=ROOT/'cases'/('v3-'+id_)
 if request.get('case_dir')!=str(case) or not case.is_dir() or case.is_symlink():
  raise ValueError('unexpected case path')
 draft=case/'decisions.draft.json'
 if not draft.is_file() or draft.is_symlink():raise ValueError('missing draft')
 data=json.loads(draft.read_text())
 proposals=[r for r in data.get('decisions',[]) if r.get('decision')=='proposed']
 if len(proposals)>40 or len({r.get('question_id') for r in proposals})!=len(proposals):
  raise ValueError('proposals duplicated or too many')
 for q in proposals:
  if not QUESTION_ID_RE.fullmatch(str(q.get('question_id',''))):raise ValueError('bad UUID')
  if q.get('key_class')!='final':raise ValueError('only final key supported in auto QA')
  if not isinstance(q.get('pdf_page'),int) or q['pdf_page']<1:
   raise ValueError('invalid pdf page')
 return request,case,draft,proposals

def strict_verdicts(out,id_,proposals):
 if not isinstance(out,dict) or out.get('batch_id')!=id_ or not isinstance(out.get('verdicts'),list):
  raise ValueError('missing structured QA verdict')
 ids={x['question_id'] for x in proposals}
 verdicts=out['verdicts']
 if len(verdicts)!=len(ids) or {q.get('question_id') for q in verdicts}!=ids:
  raise ValueError('QA did not cover each proposed question exactly once')
 seen=set();approved=[]
 for v in verdicts:
  if v['question_id'] in seen:raise ValueError('duplicate verdict')
  seen.add(v['question_id'])
  if not isinstance(v.get('reason'),str) or len(v['reason'])<18:
   raise ValueError('QA reason missing')
  fields=('answer_fully_supported','all_options_and_official_key_checked',
          'no_material_conflict','edition_policy_checked','physical_page_chapter_sha_checked')
  if not all(isinstance(v.get(k),bool) for k in fields):
   raise ValueError('invalid QA flags')
  if v.get('approved') is True and all(v[k] is True for k in fields):
   q=next(x for x in proposals if x['question_id']==v['question_id'])
   if float(q.get('confidence',0))>=.85:
    approved.append(q)
 return approved

def one(a):
 root=ROOT
 state_dir=root/'qa-state';state_dir.mkdir(parents=True,exist_ok=True,mode=0o700)
 # Worker QA is PRELIMINARY triage only. The publisher trusts solely the
 # root-attested packets that trusted_qa_worker.py writes to qa-approved/.
 approved_dir=root/'qa-preliminary';approved_dir.mkdir(parents=True,exist_ok=True,mode=0o700)
 with (state_dir/'qa.lock').open('a+') as lk:
  try: fcntl.flock(lk,fcntl.LOCK_EX|fcntl.LOCK_NB)
  except BlockingIOError: print('QA_BUSY');return 0
  state_path=state_dir/'state.json'
  state=json.loads(state_path.read_text()) if state_path.exists() else {}
  now=dt.datetime.now(UTC)
  notbefore=utc(a.not_before);deadline=utc(a.deadline)
  if now<notbefore or now>=deadline or (
      state.get('next_allowed_utc') and now<utc(state['next_allowed_utc'])):
   print(json.dumps({'status':'blocked_by_provider_reset_or_deadline','when':now.isoformat()}))
   return 0
  sp=os.statvfs(str(root))
  if sp.f_bavail*sp.f_frsize<3*1024**3:
   print('QA_DISK_GUARD');return 0
  receipts={f.stem for f in (root/'operator-receipts').glob('*.json')}
  requests=sorted((root/'operator-requests').glob('*.json'),key=lambda p:p.stat().st_mtime)
  for path in requests:
   if path.stem in receipts or (approved_dir/(path.stem+'.json')).exists():
    continue
   if state.get('failed',{}).get(path.stem,0)>=2:
    continue
   try:request,case,draft,proposals=safe_request(path)
   except (OSError,ValueError,AssertionError) as e:
    state.setdefault('failed',{})[path.stem]=2
    atomic_write(state_path,state)
    print(json.dumps({'status':'invalid_request','id':path.stem,'reason_type':type(e).__name__}))
    continue
   if a.dry_run:
    print(json.dumps({'status':'ready_for_independent_QA','id':path.stem,'proposals':len(proposals)}))
    return 0
   if not proposals:
    reviewed={'format':REVIEW_FORMAT,'batch_id':path.stem,
      'research_draft_sha256':sha(draft),'research_request_sha256':sha(path),
      'verdicts':[],'approved':[],'approved_count':0,'rejected_count':0,
      'reason':'no_proposed_sources','independent_model':'not_needed',
      'created_at_utc':now.isoformat()}
    atomic_write(approved_dir/(path.stem+'.json'),reviewed)
    print(json.dumps({'status':'qa_zero_proposals','id':path.stem}))
    return 0
   remaining=int((deadline-now).total_seconds())
   if remaining<30:return 0
   prompt=(f"You are a NEW INDEPENDENT Claude Opus scientific auditor. Researcher session was separate. "
    f"Read private V3 case {case} and exact draft {draft}. "
    f"Approve or reject every proposed question in batch {path.stem}; "
    f"use the study-YYYY.json original official questions, option wording and ALL official keys, "
    f"including also_correct and voided status. Do not use other edition without newest-first official policy. "
    f"Read exact protected PDF one physical page at a time in RAM with "
    f"/srv/fanoos/current/scripts/references/verified_reference_pdf.py, verify SHA and chapter maps, "
    f"short answer-bearing exact quote and complete official answer. "
    f"Reject if any primary official key contradicts book even if also_correct choice is supported; "
    f"reject ambiguous alternatives, negative/multiple clause lacking direct evidence, "
    f"incorrect book, page, chapter or reference-year proxy. "
    f"Return a STRICT structured JSON object with batch_id and one verdict per proposed question id; "
    f"booleans must reflect independently checked facts and reason >=18 chars. "
    f"Set approved false on ANY uncertainty. No production writes, no file modifications, no subagents. "
    f"No full book text, OCR or plaintext cache persisted. Only short reasons in final output.")
   cmd=[MODEL,'--print','--model','opus','--effort','medium','--permission-mode','auto',
     '--tools','Bash,Read','--add-dir','/srv/fanoos/shared/research',
     '--add-dir','/srv/fanoos/current','--output-format','json',
     '--max-budget-usd',str(a.budget),'--json-schema',json.dumps(SCHEMA,separators=(',',':'))]
   output=case/('qa-session-'+dt.datetime.now(UTC).strftime('%Y%m%dT%H%M%S%f')+'.json')
   fd=os.open(output,os.O_WRONLY|os.O_CREAT|os.O_EXCL,0o600)
   t=time.monotonic();rc=0;killed=False
   with os.fdopen(fd,'wb') as log:
    p=subprocess.Popen(cmd,stdin=subprocess.PIPE,stdout=log,stderr=subprocess.STDOUT,
       cwd=str(REPO),env={k:os.environ[k] for k in ENV_KEEP if k in os.environ},
       start_new_session=True,preexec_fn=lambda:os.nice(10))
    try:p.communicate(prompt.encode(),timeout=min(a.timeout,remaining))
    except subprocess.TimeoutExpired:
     killed=True;os.killpg(p.pid,signal.SIGTERM)
     try:p.wait(20)
     except subprocess.TimeoutExpired:
      os.killpg(p.pid,signal.SIGKILL);p.wait()
    rc=p.returncode
   data={}
   try:data=json.loads(output.read_text())
   except ValueError:pass
   quota,delay=scan_quota(output.read_bytes()[-8192:].decode('utf-8','replace'))
   if data.get('api_error_status')==429:quota=True
   if quota:
    state['next_allowed_utc']=(dt.datetime.now(UTC)+dt.timedelta(seconds=delay or 3600)).isoformat()
   else:state['next_allowed_utc']=(dt.datetime.now(UTC)+dt.timedelta(minutes=10)).isoformat()
   state.setdefault('cost_list_usd_estimate',0.0)
   state['cost_list_usd_estimate']=round(state['cost_list_usd_estimate']+float(data.get('total_cost_usd') or 0),4)
   result=data.get('structured_output')
   if isinstance(result,str):
    try:result=json.loads(result)
    except ValueError:result=None
   try:
    if quota or killed or rc!=0 or not isinstance(result,dict):
     raise ValueError('model_quota_timeout_or_no_structured_output')
    qa=strict_verdicts(result,path.stem,proposals)
    strictly_final=[]
    for item in qa:
     # Multiple correct keys and nonfinal status require separate clinical adjudication.
     study=json.loads((case/f"study-{item['year']}.json").read_text())
     q=next(x for x in study['questions'] if x['number']==item['number'])
     if q.get('answer',{}).get('also_correct') or q.get('answer',{}).get('status')!='final':
      continue
     strictly_final.append(item)
    qa=strictly_final
    payload={'format':REVIEW_FORMAT,'batch_id':path.stem,
     'research_draft_sha256':sha(draft),'research_request_sha256':sha(path),
     'independent_qa_session_id':data.get('session_id'),
     'independent_model':'claude-opus','qa_output_sha256':sha(output),
     'approved':qa,'approved_count':len(qa),
     'rejected_count':len(proposals)-len(qa),
     'created_at_utc':dt.datetime.now(UTC).isoformat(),
     'verdicts':result['verdicts']}
    atomic_write(approved_dir/(path.stem+'.json'),payload)
    state.setdefault('completed',{})[path.stem]=len(qa)
    print(json.dumps({'status':'independent_qa_done','id':path.stem,
       'approved':len(qa),'rejected':len(proposals)-len(qa),
       'seconds':round(time.monotonic()-t)}))
   except (ValueError,KeyError,TypeError,StopIteration) as e:
    state.setdefault('failed',{})[path.stem]=state.get('failed',{}).get(path.stem,0)+1
    print(json.dumps({'status':'qa_pending','id':path.stem,
         'reason_type':type(e).__name__,'quota':quota,'seconds':round(time.monotonic()-t)}))
   finally:
    atomic_write(state_path,state)
   return 0
  print(json.dumps({'status':'no_pending_qa_requests'}))
  return 0

def main(argv=None):
 ap=argparse.ArgumentParser()
 ap.add_argument('--not-before',default='2026-11-10T01:12:00Z')
 ap.add_argument('--deadline',default='2026-11-11T22:06:20Z')
 ap.add_argument('--timeout',type=int,default=410)
 ap.add_argument('--budget',type=float,default=2.0)
 ap.add_argument('--dry-run',action='store_true')
 a=ap.parse_args(argv)
 if a.timeout<60 or a.timeout>1200 or a.budget<=0 or a.budget>6:
  ap.error('unsafe timeout/budget')
 if os.geteuid()==0 or os.getuid()==982:
  ap.error('QA must be isolated worker only')
 return one(a)

if __name__=='__main__':
 sys.exit(main())
