#!/usr/bin/env python3
"""Read real Claude subscription usage proactively via the official CLI /usage.

Claude Code --print /usage is a zero-model-cost built-in status command as
observed on this account (2026-11-10). We do NOT use token/API list prices
as a proxy for the user's Max subscription allowance. Fail closed if status
cannot be refreshed. Never print authentication tokens or conversation text.

Separate five-hour and weekly headroom; do not run new model sessions once
75% session or 80% weekly quota is used. This is preventive and intentionally
does not guarantee that external use / one large response cannot hit 100%.
"""
from __future__ import annotations
import argparse
import datetime as dt
import fcntl
import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import time

UTC=dt.timezone.utc
STATE_DIR=Path('/srv/fanoos/shared/research/classification/v3-runner/quota')
CLAUDE=Path('/mnt/workspace/home/bin/claude')
SESSION_RE=re.compile(r'^Current session:\s*(\d{1,3})%\s*used\b(.*)$',re.I)
WEEK_RE=re.compile(r'^Current week \(all models\):\s*(\d{1,3})%\s*used\b(.*)$',re.I)
RESET_RE=re.compile(r'\bresets\s+([A-Za-z]{3})\s+(\d{1,2}),\s*(\d{1,2}):?(\d{2})?\s*(am|pm)\s*\(UTC\)',re.I)
MONTHS={dt.date(2026,m,1).strftime('%b').lower():m for m in range(1,13)}
ALLOW_ENV=('PATH','HOME','LANG','LC_ALL','TZ','USER','LOGNAME','CLAUDE_CONFIG_DIR')

def reset_time(span,now):
 m=RESET_RE.search(span)
 if not m:return None
 name,day,hour,minute,suffix=m.groups()
 month=MONTHS.get(name.lower())
 if not month:return None
 day=int(day);hour=int(hour);minute=int(minute or 0)
 if not 1<=hour<=12 or not 0<=minute<=59:return None
 hour=hour%12+(12 if suffix.lower()=='pm' else 0)
 try:candidate=dt.datetime(now.year,month,day,hour,minute,tzinfo=UTC)
 except ValueError:return None
 if candidate < now-dt.timedelta(days=2):
  candidate=candidate.replace(year=now.year+1)
 return candidate.isoformat()

def parse_status(raw,now=None):
 if now is None:now=dt.datetime.now(UTC)
 if not isinstance(raw,str) or len(raw)>120000:
  raise ValueError('invalid usage response length')
 data=json.loads(raw)
 if not isinstance(data,dict) or data.get('is_error') or data.get('api_error_status'):
  raise ValueError('usage command returned API error')
 if data.get('type')!='result' or data.get('total_cost_usd') not in (0,0.0,None):
  # For maximum protection, never accept a "usage" fetch that invokes a
  # model or creates metered usage.
  raise ValueError('not a free built-in /usage status response')
 content=data.get('result')
 if not isinstance(content,str):raise ValueError('missing /usage text')
 five=weekly=None
 resets={}
 for line in content.splitlines():
  line=line.strip()
  for name,rgx in (('five_hour',SESSION_RE),('week_all',WEEK_RE)):
   m=rgx.match(line)
   if not m:continue
   pct=int(m.group(1))
   if not 0<=pct<=100:raise ValueError('percent invalid')
   if name=='five_hour':five=pct
   else:weekly=pct
   resets[name]=reset_time(m.group(2),now)
 if five is None or weekly is None:
  raise ValueError('both 5h and weekly subscription usage percentages are required')
 # Immediately after a session resets, Claude may emit "Current session:
 # 0% used" without the next reset timestamp. This is a legitimate
 # zero-usage window. Never guess the next reset time: check again in
 # max 300s; once usage rises the provider normally reports its clock.
 if resets.get('five_hour') is None and five!=0:
  raise ValueError('five-hour reset missing for nonzero usage')
 if resets.get('week_all') is None and weekly!=0:
  raise ValueError('weekly reset missing for nonzero usage')
 return {'five_hour_pct':five,'week_all_pct':weekly,'resets_utc':resets,
         'fetched_at_utc':now.isoformat(),'source':'claude_code_builtin_slash_usage',
         'model_cost_usd':0}

def atomic_json(path,data):
 fd,tmp=tempfile.mkstemp(dir=path.parent,prefix='.quota-safe-')
 try:
  os.fchmod(fd,0o600)
  with os.fdopen(fd,'w',encoding='utf-8') as f:
   json.dump(data,f,ensure_ascii=False,indent=2,sort_keys=True)
   f.write('\n');f.flush();os.fsync(f.fileno())
  os.replace(tmp,path)
 finally:
  if os.path.exists(tmp):os.unlink(tmp)

def status_with_cache(max_age,timeout,force=False):
 STATE_DIR.mkdir(parents=True,exist_ok=True,mode=0o700)
 if STATE_DIR.is_symlink():raise ValueError('quota state is symlink')
 if STATE_DIR.stat().st_uid!=os.geteuid():raise ValueError('quota directory owner not worker')
 with (STATE_DIR/'lock').open('a+') as f:
  fcntl.flock(f,fcntl.LOCK_EX)
  p=STATE_DIR/'last.json'
  now=dt.datetime.now(UTC)
  if p.exists():
   if p.is_symlink():raise ValueError('quota snapshot symlink')
   d=json.loads(p.read_text())
   then=dt.datetime.fromisoformat(d['fetched_at_utc'])
   age=(now-then).total_seconds()
   if not force and 0<=age<max_age:
    return d,'cached'
  cmd=[str(CLAUDE),'--print','/usage','--output-format','json',
       '--max-turns','1','--tools','']
  proc=subprocess.run(cmd,capture_output=True,text=True,timeout=timeout,
      cwd='/mnt/workspace/FanoosLearn-classifier-v3',
      env={k:v for k,v in os.environ.items() if k in ALLOW_ENV})
  if proc.returncode:raise ValueError('provider usage check unavailable')
  data=parse_status(proc.stdout,now)
  atomic_json(p,data)
  return data,'fresh'

def check(d,session_limit=75,week_limit=80,now=None):
 if not 10<=session_limit<=95 or not 10<=week_limit<=95:
  raise ValueError('unsafe reserve threshold')
 five,weekly=d['five_hour_pct'],d['week_all_pct']
 now=now or dt.datetime.now(UTC)
 reasons=[]
 if five>=session_limit:
  reasons.append('five_hour_headroom_reserved')
 if weekly>=week_limit:
  reasons.append('weekly_headroom_reserved')
 for kind in ('five_hour','week_all'):
  value=d['resets_utc'].get(kind)
  if not value:
   if d[kind+'_pct']!=0:
    reasons.append('missing_nonzero_reset')
   continue
  reset=dt.datetime.fromisoformat(value)
  if reset<now-dt.timedelta(minutes=10):
   reasons.append('stale_usage_reset')
 return not reasons,reasons

def main(argv=None):
 ap=argparse.ArgumentParser()
 ap.add_argument('--check',action='store_true')
 ap.add_argument('--status',action='store_true')
 ap.add_argument('--force',action='store_true')
 ap.add_argument('--max-age-sec',type=int,default=300)
 ap.add_argument('--timeout-sec',type=int,default=28)
 ap.add_argument('--session-limit',type=int,default=75)
 ap.add_argument('--week-limit',type=int,default=80)
 a=ap.parse_args(argv)
 if os.geteuid()==0:ap.error('run in Claude worker sandbox, never as root')
 if not (60<=a.max_age_sec<=900 and 4<=a.timeout_sec<=40):
  ap.error('invalid cache/timeout')
 if not (a.check or a.status):ap.error('choose --check or --status')
 try:
  d,source=status_with_cache(a.max_age_sec,a.timeout_sec,a.force)
  ok,reasons=check(d,a.session_limit,a.week_limit)
  print(json.dumps({'status':'allow' if ok else 'reserve_pause',
     'five_hour_pct':d['five_hour_pct'],'week_all_pct':d['week_all_pct'],
     'session_limit':a.session_limit,'week_limit':a.week_limit,
     'reset_5h_utc':d['resets_utc']['five_hour'],
     'reset_week_utc':d['resets_utc']['week_all'],
     'checked_at_utc':d['fetched_at_utc'],'cached':source=='cached',
     'reason':','.join(reasons)},ensure_ascii=False))
  if a.check and not ok:return 1
  return 0
 except (OSError,ValueError,KeyError,TypeError,subprocess.TimeoutExpired) as e:
  print(json.dumps({'status':'fail_closed_usage_unavailable',
    'error_type':type(e).__name__}))
  return 1 if a.check else 2

if __name__=='__main__':
 sys.exit(main())
