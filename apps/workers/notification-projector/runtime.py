#!/usr/bin/env python3
from __future__ import annotations
import logging,os,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3];sys.path.insert(0,str(ROOT/'packages/python'))
from fanoos_bot.api import FanoosApiClient

def required(n):
    v=os.getenv(n,'').strip()
    if not v:raise RuntimeError(f'{n} is required')
    return v
def build():return FanoosApiClient(required('FANOOS_API_ORIGIN'),required('FANOOS_NOTIFICATION_SERVICE_KEY_ID'),required('FANOOS_NOTIFICATION_SERVICE_SECRET'))
def main():
    logging.basicConfig(level=os.getenv('LOG_LEVEL','INFO'),format='%(asctime)s %(levelname)s %(message)s');api=build();idle=max(1,min(60,int(os.getenv('FANOOS_NOTIFICATION_IDLE_SECONDS','3'))))
    while True:
        try:
            result=api.project_notifications();event_id=result.get('event_id') if isinstance(result,dict) else None
            if not event_id:time.sleep(idle)
        except Exception as exc:logging.error('notification projector iteration failed type=%s',type(exc).__name__);time.sleep(idle)
if __name__=='__main__':main()
