#!/usr/bin/env python3
from __future__ import annotations
import os,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3];sys.path.insert(0,str(ROOT/'packages/python'))
from fanoos_bot.api import FanoosApiClient
from worker import JobProcessor,PrivateSpoolArtifactSink,WorkerFailure

def required(n):
    v=os.getenv(n,'').strip()
    if not v:raise RuntimeError(f'{n} is required')
    return v

def build():
    raise RuntimeError('Protected-media worker blocked: canonical object-capability redemption adapter is not frozen')

def run_once(api,processor):
    projection=api.media_claim();job=projection.get('job') if isinstance(projection,dict) else None
    if not job:return False
    try:result=processor.process(job);api.media_complete(job['job_id'],job['lease_token'],job['completion_key'],result)
    except WorkerFailure as exc:api.media_fail(job['job_id'],job['lease_token'],exc.code)
    except Exception:api.media_fail(job['job_id'],job['lease_token'],'internal_error')
    return True

def main():build()
if __name__=='__main__':main()
