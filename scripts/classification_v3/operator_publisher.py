#!/usr/bin/env python3
"""Host-reviewed, fail-closed V3 autonomous source-only publisher.

Run as root from a ROOT-OWNED IMMUTABLE installed copy, not checkout.
All production operations are delegated to fanoosupd through the existing
guarded source-only API; this module itself issues NO SQL writes.
Worker research and Opus QA output are untrusted until independently
re-exported and validated against same SHA-bound PDF and official catalog.
"""
from __future__ import annotations
import argparse
import datetime as dt
import hashlib
import json
import os
from pathlib import Path
import pwd
import re
import shutil
import stat
import subprocess
import sys
import tempfile
import time
import fcntl
from collections import defaultdict

UTC=dt.timezone.utc
BASE=Path('/srv/fanoos/shared/research/classification/v3-runner')
CASES=Path('/srv/fanoos/shared/research/classification')
PROD=Path('/srv/fanoos/current')
BACKUPS=Path('/var/backups/fanoos')
QA_FORMAT='fanoos.classifier-v3.independent-opus-qa.v1'
RECEIPT_FORMAT='fanoos.classifier-v3.operator-status.v1'
UUID_RE=re.compile(r'^[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}$')
HEXID=re.compile(r'^[a-f0-9]{16}$')
EDITION_RE=re.compile(r'^[a-z0-9][a-z0-9._-]{0,80}@[a-z0-9][a-z0-9._-]{0,40}$')
SUBJECT_RE=re.compile(r'^[a-z0-9][a-z0-9-]{0,59}$')
OPS_USER=pwd.getpwnam('fanoosupd')
WS='01a1037b-0322-7c8f-8904-7c9334fb9cbe'
EXPECTED_RELEASE='127fe9e4450564903d03042301ed0d39080b7122'
PINNED_TRUSTED_SCRIPTS={
 'scripts/references/import_verified_sources.php':'b40999bffb610169bdcbe0cc3444b49de516dd3f87139c1d04fb82b833c29866',
 'scripts/references/audit_private_study_batches.py':'dcd458af8b715173c01cdea4c4e6859d094a713e911fc2a36fae179a448bab4c',
 'scripts/references/apply_classification.py':'7295dae2bb27079d2072aff26492c291528e7b98c73c90827092299eb8a85f7e',
 'scripts/references/export_server_candidates.php':'6d2dd257da9dcc6c3328ece43387570518bbba33be326faecf76392d31424799',
 'scripts/ops/backup.php':'3fc989aa74da8f7da6c401dd10e8e40b2c41a736ae8826b8b74f09bdb9a7f6cf',
 'scripts/ops/verify-backup.php':'db36730dbe23f5658ccba9432207d60751bf7c1536ff6f17f8643de2452dce14',
}
FORBIDDEN_QS={(1391,'endodontics',178)} # main official key contradictory to cited excerpt

class Deferred(Exception):pass
class Unsafe(Exception):pass

def utc(value):
 d=dt.datetime.fromisoformat(value.replace('Z','+00:00'))
 if d.tzinfo is None:raise Unsafe('UTC required')
 return d.astimezone(UTC)

def check_trusted_release():
 if PROD.resolve().name!=EXPECTED_RELEASE:
  raise Deferred('production release changed, host review required')
 for relative,digest in PINNED_TRUSTED_SCRIPTS.items():
  p=PROD/relative
  if not p.is_file() or sha(p)!=digest:
   raise Deferred('trusted operator script changed, host review required')

def check_deadline(a):
 now=dt.datetime.now(UTC)
 if now<utc(a.not_before) or now>=utc(a.deadline):
  raise Deferred('before provider reset or after 48h deadline')
 free=shutil.disk_usage(BASE).free
 if free<3*1024**3:
  raise Deferred('free disk below 3GiB')

def sha(p):return hashlib.sha256(p.read_bytes()).hexdigest()

def load(p):
 if p.is_symlink() or not p.is_file():raise Unsafe('missing/symlink protected file')
 return json.loads(p.read_text('utf-8'))

ROOT_STATE=Path('/var/lib/fanoos-v3-operator')
QA_SESSIONS=ROOT_STATE/'qa-sessions'
QA_ATTESTATIONS=ROOT_STATE/'qa-attestations'
ROOT_UID=0

def root_only(p):
 """Load a file only if it and its parent are root-owned and not group/world-writable."""
 for x in (p.parent,p):
  st=x.lstat()
  if stat.S_ISLNK(st.st_mode) or st.st_uid!=ROOT_UID or st.st_mode & (stat.S_IWGRP|stat.S_IWOTH):
   raise Unsafe('trust anchor is not root-only')
 return load(p)

def mkdir_operator(p):
 p.mkdir(parents=False,exist_ok=False,mode=0o700)
 os.chown(p,OPS_USER.pw_uid,OPS_USER.pw_gid)
 os.chmod(p,0o700)

def make_tree(case):
 mkdir_operator(case)
 for part in ('bank-sittings','classification','classification/decisions',
              'classification/sittings','classification/reports'):
  pp=case/part;pp.mkdir(mode=0o700)
  os.chown(pp,OPS_USER.pw_uid,OPS_USER.pw_gid);os.chmod(pp,0o700)

def op(cmd,timeout=180):
 env={'PATH':'/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/bin',
      'LANG':'C.UTF-8','FANOOS_CONFIG_FILE':'/etc/fanoos/updater-config.php',
      'PYTHONDONTWRITEBYTECODE':'1'}
 def drop():
  os.initgroups(OPS_USER.pw_name,OPS_USER.pw_gid)
  os.setgid(OPS_USER.pw_gid)
  os.setuid(OPS_USER.pw_uid)
 proc=subprocess.run(cmd,cwd=str(PROD),env=env,preexec_fn=drop,
     timeout=timeout,capture_output=True,text=True)
 if proc.returncode:
  # Never log private question text nor raw source data.
  raise Unsafe('trusted operator command refused: '+Path(cmd[1] if len(cmd)>1 else cmd[0]).name+
               ' exit '+str(proc.returncode))
 return proc.stdout.strip()

def operator_file(path,bytes_):
 if path.exists():raise Unsafe('operator case immutable file exists')
 with path.open('xb') as f:f.write(bytes_);f.flush();os.fsync(f.fileno())
 os.chown(path,OPS_USER.pw_uid,OPS_USER.pw_gid);os.chmod(path,0o600)

def verify_research_qa(batchid):
 request_p=BASE/'operator-requests'/f'{batchid}.json'
 qa_p=BASE/'qa-approved'/f'{batchid}.json'
 request=load(request_p);qa=load(qa_p)
 assert request['batch_id']==batchid and request['format']=='fanoos.classifier-v3.operator-request.v1'
 assert qa['format']==QA_FORMAT and qa['batch_id']==batchid
 case=BASE/'cases'/('v3-'+batchid)
 if request.get('case_dir')!=str(case) or not case.is_dir() or case.is_symlink():
  raise Unsafe('research case escapes fixed root')
 draft_p=case/'decisions.draft.json'
 if qa.get('research_draft_sha256')!=sha(draft_p) or qa.get('research_request_sha256')!=sha(request_p):
  raise Unsafe('QA input SHA changed since independent review')
 # Worker-writable files prove nothing: require the root-only attestation
 # written by trusted_qa_worker.py, pinning packet, draft, request and the
 # root-captured model output.
 att=root_only(QA_ATTESTATIONS/f'{batchid}.json')
 if (att.get('batch_id')!=batchid or att.get('qa_packet_sha256')!=sha(qa_p)
     or att.get('draft_sha256')!=sha(draft_p) or att.get('request_sha256')!=sha(request_p)
     or att.get('approved_count')!=qa.get('approved_count')):
  raise Unsafe('QA packet does not match root attestation')
 draft=load(draft_p)
 proposals={r['question_id']:r for r in draft.get('decisions',[]) if r.get('decision')=='proposed'}
 if len(proposals)>40 or len(proposals)!=len(set(proposals)):
  raise Unsafe('unbounded or duplicate research proposals')
 if qa.get('approved_count')!=len(qa.get('approved',[])):
  raise Unsafe('approval count mismatch')
 if not isinstance(qa.get('verdicts'),list):
  raise Unsafe('QA evidence missing')
 verdict={v['question_id']:v for v in qa['verdicts']}
 if set(verdict)!=set(proposals) or len(verdict)!=len(qa['verdicts']):
  raise Unsafe('second Opus did not decide every proposed source')
 if not proposals and qa.get('approved_count')==0 and qa.get('independent_model')=='not_needed':
  return request,case,[]
 if not qa.get('independent_qa_session_id'):
  raise Unsafe('no independent model session identifier')
 research_out=load(case/'session.out')
 research_id=research_out.get('session_id')
 if not research_id or research_id==qa['independent_qa_session_id']:
  raise Unsafe('scientific reviewer is not an independent Opus session')
 if (not qa.get('qa_output_sha256') or att.get('model_output_sha256')!=qa['qa_output_sha256']
     or att.get('session_id')!=qa['independent_qa_session_id']):
  raise Unsafe('model output not pinned by root attestation')
 qa_output=QA_SESSIONS/f'{batchid}.json'
 model_record=root_only(qa_output)
 if sha(qa_output)!=qa['qa_output_sha256']:
  raise Unsafe('missing SHA-bound independent model output')
 if model_record.get('session_id')!=qa['independent_qa_session_id'] or model_record.get('is_error'):
  raise Unsafe('independent model receipt invalid')
 if not any('opus' in m.lower() for m in (model_record.get('modelUsage') or {})):
  raise Unsafe('independent model is not Opus')
 structured=model_record.get('structured_output')
 if isinstance(structured,str):
  structured=json.loads(structured)
 if not isinstance(structured,dict) or structured.get('batch_id')!=batchid:
  raise Unsafe('model structured QA output invalid')
 actual={v['question_id']:v for v in structured.get('verdicts',[])}
 if actual!=verdict:raise Unsafe('scientific verdict differs from model output')
 approved=[]
 for r in qa['approved']:
  uid=r.get('question_id')
  if uid not in proposals or r!=proposals[uid] or not UUID_RE.fullmatch(str(uid)):
   raise Unsafe('QA approved a non-proposed source')
  v=verdict[uid]
  allchecks=('answer_fully_supported','all_options_and_official_key_checked',
     'no_material_conflict','edition_policy_checked','physical_page_chapter_sha_checked')
  if v.get('approved') is not True or not all(v.get(k) is True for k in allchecks):
   raise Unsafe('approved source missing independent full-answer checks')
  if float(r.get('confidence',0))<0.85:
   raise Unsafe('below 0.85 acceptance threshold')
  if (r.get('year'),r.get('subject'),r.get('number')) in FORBIDDEN_QS:
   raise Unsafe('conflicting official key cannot auto-import')
  if r.get('key_class')!='final':
   raise Unsafe('key must be officially final')
  if r.get('edition') not in request.get('selected_editions',[]):
   raise Unsafe('source not from selected edition')
  if r.get('basis')!=request.get('proxy_basis'):
   raise Unsafe('proxy policy mismatch')
  if not EDITION_RE.fullmatch(str(r.get('edition',''))) or not SUBJECT_RE.fullmatch(str(r.get('subject',''))):
   raise Unsafe('invalid subject or edition key')
  if not isinstance(r.get('number'),int) or r['number']<1 or r['number']>300:
   raise Unsafe('invalid question number')
  if not isinstance(r.get('pdf_page'),int) or r['pdf_page']<1:
   raise Unsafe('physical page missing')
  if not isinstance(r.get('node_id'),(str,int)):
   raise Unsafe('chapter not mapped')
  if not isinstance(r.get('quote'),str) or len(r['quote'].split())<4 or len(r['quote'])>300:
   raise Unsafe('short exact evidence absent')
  if not isinstance(r.get('reference_year'),int) or r['reference_year']<1397:
   raise Unsafe('official reference year missing')
  approved.append(r)
 if len(approved)>40 or len({r['question_id'] for r in approved})!=len(approved):
  raise Unsafe('duplicate approved sources')
 return request,case,approved

def proc_group(req,group):
 kind,year,rnd,sub,basis=group[0]['exam_type'],group[0]['year'],group[0]['round'],group[0]['subject'],group[0]['basis']
 if kind not in ('residency','national') or not SUBJECT_RE.fullmatch(sub):
  raise Unsafe('unsupported group')
 if kind=='residency' and rnd!=1:raise Unsafe('invalid residency round')
 if not (1391<=year<=1500 and 1<=rnd<=9):
  raise Unsafe('invalid year/round')
 for r in group:
  if (r['exam_type'],r['year'],r['round'],r['subject'],r['basis'])!=(kind,year,rnd,sub,basis):
   raise Unsafe('mixed policy group')
 if kind=='residency' and year<=1395 and basis!='retrospective_proxy_1397_plus':
  raise Unsafe('old residency needs explicit retrospective basis')
 if kind=='residency' and 1396<=year<=1398 and basis!='same_lineage_proxy':
  raise Unsafe('1396-98 residency requires same-lineage proxy')
 if kind=='residency' and year>=1399 and basis!='official_exam_year':
  raise Unsafe('new residency must use an explicitly verified official exam-year reference')
 if kind=='national' and basis not in ('official_exam_year','same_lineage_proxy'):
  raise Unsafe('national source basis unsupported')
 return kind,year,rnd,sub,basis

def get_study_from_db(kind,year,rnd,sub,out):
 args=['php',str(PROD/'scripts/references/export_server_candidates.php'),
       '--workspace='+WS,'--type='+kind,'--year='+str(year),
       '--round='+str(rnd),'--subject='+sub,'--out='+str(out)]
 op(args,timeout=90)
 study=load(out)
 if study.get('exam_type')!=kind or study.get('year')!=year or study.get('round')!=rnd:
  raise Unsafe('current DB study identity mismatch')
 return study

def safe_backup():
 free=shutil.disk_usage(BACKUPS).free
 if free<3*1024**3+250*1024**2:
  raise Deferred('disk guard before full backup')
 now=dt.datetime.now(UTC)
 options=[]
 for p in BACKUPS.iterdir():
  if not p.is_dir() or not re.fullmatch(r'20\d{6}T\d{6}Z-[a-f0-9]{8}',p.name):
   continue
  manifest=p/'manifest.json'
  if not manifest.is_file() or not (p/'READY').is_file():continue
  m=load(manifest)
  try:created=utc(m['created_at'])
  except (KeyError,TypeError,ValueError):continue
  if dt.timedelta(0)<=now-created<dt.timedelta(hours=3,minutes=15):
   options.append((created,p))
 if options:
  p=max(options)[1]
 else:
  created=op(['php',str(PROD/'scripts/ops/backup.php')],timeout=260)
  p=Path(created.splitlines()[-1])
  if not p.is_dir() or p.parent!=BACKUPS:raise Unsafe('backup outside authorized root')
 op(['php',str(PROD/'scripts/ops/verify-backup.php'),str(p)],timeout=160)
 return p

def save_operator_receipt(batchid,approved_n,applied_receipts,qa_sha,reason=''):
 p=BASE/'operator-receipts'/f'{batchid}.json'
 if p.exists():return
 payload={'format':RECEIPT_FORMAT,'batch_id':batchid,
   'independent_qa':'approved_or_rejected',
   'readback_verified':True,'accepted_source_rows':approved_n,
   'backing_verified_source_only_receipts':applied_receipts,
   'qa_approved_packet_sha256':qa_sha,
   'owner_status':'source_only_readback_verified' if approved_n else 'no_source_accepted',
   'reason_code':reason,'completed_at_utc':dt.datetime.now(UTC).isoformat()}
 data=(json.dumps(payload,ensure_ascii=False,sort_keys=True,indent=2)+'\n').encode()
 fd,tmp=tempfile.mkstemp(prefix='.operator-status-',dir=p.parent)
 try:
  os.fchown(fd,OPS_USER.pw_uid,pwd.getpwnam('fanoosworker').pw_gid)
  os.fchmod(fd,0o640)
  with os.fdopen(fd,'wb') as f:f.write(data);f.flush();os.fsync(f.fileno())
  os.replace(tmp,p)
 except:
  try:os.unlink(tmp)
  except FileNotFoundError:pass
  raise

def process_group(request,group,bid):
 kind,year,rnd,sub,basis=proc_group(request,group)
 stamp=dt.datetime.fromtimestamp(int(request['created']),UTC).strftime('%Y%m%d')
 case=CASES/f'recovery-{year}-v3-operator-{bid}-{stamp}'
 if case.is_symlink() or (case.exists() and (
    case.resolve().parent!=CASES.resolve() or
    case.stat().st_uid!=OPS_USER.pw_uid or
    case.stat().st_mode & (stat.S_IWGRP | stat.S_IWOTH))):
  raise Unsafe('operator case identity or permissions unsafe')
 stem=(f'same-lineage-proxy-{bid}' if basis=='same_lineage_proxy'
       else f'{kind[:3]}v3-{bid}')
 study_path=case/'bank-sittings'/str(year)/(stem+'-study.json')
 validated=case/'classification/sittings'/(str(year)+'-'+stem+'-validated.json')
 decisions_path=(case/'classification/decisions'/
   (f'{year}-{sub}.json' if kind=='residency'
    else f'{kind}-{year}-{rnd}/{year}-{sub}.json'))
 audit=case/'classification/reports'/(f'{year}-{stem}-audit.json')
 signed=case/'classification/reports'/(f'{year}-{stem}-source-only-apply.json')
 if signed.exists():
  prior=load(signed)
  if prior.get('status')!='applied_verified' or not prior.get('readback',{}).get('verified'):
   raise Unsafe('existing source receipt not verified')
  return prior['source_rows'],{'path':str(signed),'sha256':sha(signed),'source_rows':prior['source_rows']}
 if not case.exists():
  make_tree(case)
  nested=case/'bank-sittings'/str(year)
  nested.mkdir(mode=0o700);os.chown(nested,OPS_USER.pw_uid,OPS_USER.pw_gid);os.chmod(nested,0o700)
  if kind!='residency':
   nested=case/'classification/decisions'/f'{kind}-{year}-{rnd}'
   nested.mkdir(mode=0o700);os.chown(nested,OPS_USER.pw_uid,OPS_USER.pw_gid);os.chmod(nested,0o700)
  current=get_study_from_db(kind,year,rnd,sub,study_path)
  worker_study=load(BASE/'cases'/('v3-'+bid)/f'study-{year}.json')
  live={q['number']:q for q in current['questions']}
  old={q['number']:q for q in worker_study['questions']}
  decisions=[]
  for q in group:
   n=q['number']
   if n not in live:continue  # source already exists: exclude without re-import
   if n not in old or live[n]!=old[n]:
    raise Unsafe('question changed since researcher exported it')
   if live[n].get('sources'):
    continue
   answer=live[n].get('answer') or {}
   if answer.get('status')!='final' or answer.get('also_correct'):
    continue
   val={'number':n,'edition':q['edition'],
     'chapter':str(q['node_id']),'page':q['pdf_page'],
     'confidence':float(q['confidence']),'evidence':q['quote'],
     'reference_year':q['reference_year'],
     'classification_basis':q['basis'],
     'proxy_note':q.get('note_fa') or ''}
   if q.get('newer_editions_searched'):
    val['newer_editions_searched']=q['newer_editions_searched']
   decisions.append(val)
  if not decisions:
   # Safe cleanup: only the NEW empty protected operator case was created.
   if case.resolve().parent==CASES.resolve() and case.name.startswith(f'recovery-{year}-v3-operator-{bid}-'):
    shutil.rmtree(case)
   return 0,{'source_rows':0,'reason':'all_approved_questions_already_sourced_or_key_not_final'}
  operator_file(decisions_path,(json.dumps(decisions,ensure_ascii=False,indent=2)+'\n').encode())
 else:
  if not study_path.is_file() or not decisions_path.is_file():
   raise Unsafe('existing operator case incomplete; no overwrite allowed')
  current=load(study_path)
  decisions=load(decisions_path)
  if len(decisions)>len(group) or not decisions:
   raise Unsafe('invalid existing operator case')
 if not validated.exists():
  op(['python3','-B',str(PROD/'scripts/references/apply_classification.py'),
      '--sitting='+str(study_path),'--decisions='+str(decisions_path),
      '--out='+str(validated),'--partial','--pdf-pages'],timeout=200)
 good={q['number'] for q in load(validated)['questions'] if q.get('sources')}
 if not good:
  raise Deferred('no candidate passed trusted exact-PDF validator')
 if good!={x['number'] for x in decisions}:
  raise Deferred('partial validation, requires targeted reviewed operator case')
 if not audit.exists():
  op(['python3','-B',str(PROD/'scripts/references/audit_private_study_batches.py'),
      '--local='+str(case),
      '--batch='+(f'{kind}:{year}:{rnd}:{sub}:{stem}'),
      '--out='+str(audit)],timeout=210)
 d=load(audit)
 if d.get('accepted')!=len(good) or len(d.get('batches',[]))!=1 or not d['batches'][0].get('question_content_identical'):
  raise Unsafe('trusted provenance audit mismatch')
 digest=sha(audit)
 prefix=['php',str(PROD/'scripts/references/import_verified_sources.php'),
         '--workspace='+WS,'--type='+kind,'--year='+str(year),
         '--round='+str(rnd),'--subject='+sub,'--stem='+stem,
         '--expected='+str(len(good)),'--audit='+str(audit),
         '--audit-sha='+digest,'--case-root='+str(case)]
 preview=json.loads(op(prefix,timeout=120))
 if preview.get('source_rows')!=len(good) or preview.get('applied') is not False or any(
  preview.get(k)!=0 for k in ('questions_changed','choices_changed','answers_changed')):
  raise Unsafe('source-only preview failed')
 backup=safe_backup()
 if dt.datetime.now(UTC)>=utc('2026-11-11T22:06:20Z'):
  raise Deferred('48h deadline elapsed before source-only production write')
 check_trusted_release()  # fail closed on deployment between preview and write
 applied=json.loads(op(prefix+['--apply','--backup='+str(backup),
          '--receipt='+str(signed)],timeout=150))
 if applied.get('status')!='applied_verified' or applied.get('applied') is not True or applied.get('source_rows')!=len(good):
  raise Unsafe('trusted source-only apply did not confirm')
 readback=applied.get('readback') or {}
 if not readback.get('verified') or readback.get('mismatches') or readback.get('source_field_mismatches'):
  raise Unsafe('independent DB readback mismatch')
 if readback.get('question_key_fingerprint_sha256')!=readback.get('expected_question_key_fingerprint_sha256'):
  raise Unsafe('question/option/key content mutated')
 return len(good),{'path':str(signed),'sha256':sha(signed),'source_rows':len(good)}

def process(id_,a):
 req,case,approved=verify_research_qa(id_)
 qa_path=BASE/'qa-approved'/f'{id_}.json'
 qa_sha=sha(qa_path)
 groups=defaultdict(list)
 for r in approved:
  key=(r['exam_type'],r['year'],r['round'],r['subject'],r['basis'])
  groups[key].append(r)
 if a.dry_run:
  print(json.dumps({'batch_id':id_,'qa_approved':len(approved),
    'groups':len(groups),'action':'no_writes'}))
  return
 successes=[];n=0
 for key,items in sorted(groups.items()):
  new,receipt=process_group(req,items,id_)
  n+=new;successes.append(receipt)
 save_operator_receipt(id_,n,successes,qa_sha,'none_proposed' if not groups else '')
 print(json.dumps({'status':'operator_completed','batch_id':id_,
       'source_rows_committed':n,'groups':len(groups)}))

def protected_state():
 if not ROOT_STATE.exists():
  ROOT_STATE.mkdir(mode=0o700)
 if ROOT_STATE.is_symlink() or ROOT_STATE.stat().st_uid!=0:
  raise Unsafe('host publisher state is not root-owned')
 os.chmod(ROOT_STATE,0o700)
 path=ROOT_STATE/'state.json'
 return load(path) if path.exists() else {'failed':{}}

def save_protected_state(data):
 path=ROOT_STATE/'state.json'
 fd,t=tempfile.mkstemp(prefix='.v3-op-',dir=ROOT_STATE)
 try:
  os.fchmod(fd,0o600)
  with os.fdopen(fd,'w',encoding='utf-8') as f:
   json.dump(data,f,indent=1,sort_keys=True)
   f.flush();os.fsync(f.fileno())
  os.replace(t,path)
 except:
  try:os.unlink(t)
  except FileNotFoundError:pass
  raise

def main():
 ap=argparse.ArgumentParser()
 ap.add_argument('--dry-run',action='store_true')
 ap.add_argument('--apply',action='store_true')
 ap.add_argument('--deadline',default='2026-11-11T22:06:20Z')
 ap.add_argument('--not-before',default='2026-11-10T01:12:00Z')
 ap.add_argument('--max-requests',type=int,default=4)
 a=ap.parse_args()
 if os.geteuid()!=0:ap.error('host-owned trusted publisher only')
 if a.apply==a.dry_run:ap.error('choose exactly one of --dry-run or --apply')
 if not 1<=a.max_requests<=6:ap.error('max requests must be 1..6')
 if a.apply:
  try:
   check_deadline(a)
   check_trusted_release()
  except Deferred as e:
   print(json.dumps({'status':'deferred','reason':str(e)}));return 0
 receipt_dir=BASE/'operator-receipts'
 lock=Path('/run/lock/fanoos-v3-publisher.lock')
 with lock.open('a') as lk:
  try:fcntl.flock(lk,fcntl.LOCK_EX|fcntl.LOCK_NB)
  except BlockingIOError:
   print('operator_already_running');return 0
  state=protected_state() if a.apply else {'failed':{}}
  tried=0
  for p in sorted((BASE/'qa-approved').glob('*.json'),key=lambda f:f.stat().st_mtime):
   if not HEXID.fullmatch(p.stem) or (receipt_dir/p.name).exists():
    continue
   if state.get('failed',{}).get(p.stem,0)>=3:
    continue # quarantined; host admin or Opus can inspect logs, not repeated blindly
   if a.dry_run:
    try:process(p.stem,a)
    except Exception as e:
     print(json.dumps({'status':'invalid','id':p.stem,'error_type':type(e).__name__}))
    return 0
   tried+=1
   try:
    process(p.stem,a)
    state.setdefault('failed',{}).pop(p.stem,None)
   except (Unsafe,Deferred,AssertionError,ValueError,KeyError,TypeError,subprocess.TimeoutExpired) as e:
    n=state.setdefault('failed',{}).get(p.stem,0)+1
    state['failed'][p.stem]=n
    # Failed cases remain pending with no trusted receipt. Continue other jobs.
    print(json.dumps({'status':'pending_operator_review','batch_id':p.stem,
           'attempts':n,'error_type':type(e).__name__,'reason':str(e)[:160]}))
   save_protected_state(state)
   if tried>=a.max_requests:
    break
  if not tried:
   print(json.dumps({'status':'no_operator_ready_batches_or_all_quarantined'}))
 return 0

if __name__=='__main__':
 sys.exit(main())
