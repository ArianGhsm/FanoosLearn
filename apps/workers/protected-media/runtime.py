#!/usr/bin/env python3
from __future__ import annotations
import logging,os,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[3];sys.path.insert(0,str(ROOT/'packages/python'))
from fanoos_bot.api import FanoosApiClient,FanoosApiError
from worker import ApiArtifactSink,ApiCapabilitySource,JobProcessor,PopplerPillowRasterizer,WorkerFailure

def required(n):
    v=os.getenv(n,'').strip()
    if not v:raise RuntimeError(f'{n} is required')
    return v

def build():
    api=FanoosApiClient(required('FANOOS_API_ORIGIN'),required('FANOOS_PROTECTED_MEDIA_SERVICE_KEY_ID'),required('FANOOS_PROTECTED_MEDIA_SERVICE_SECRET'))
    temp=Path(os.getenv('FANOOS_PROTECTED_MEDIA_TEMP','/var/lib/fanoos/protected-media/tmp')).resolve();temp.mkdir(parents=True,exist_ok=True);os.chmod(temp,0o700)
    font=os.getenv('FANOOS_PROTECTED_MEDIA_FONT_PATH','').strip() or None
    processor=JobProcessor(ApiCapabilitySource(api),ApiArtifactSink(api),rasterizer=PopplerPillowRasterizer(font_path=font),temp_root=temp)
    return api,processor

def _safe_failure(api,job,code):
    try:api.media_fail(job['job_id'],job['lease_token'],code)
    except Exception:pass

def run_once(api,processor):
    projection=api.media_claim();job=projection.get('job') if isinstance(projection,dict) else None
    if not job:return False
    try:
        result=processor.process(job);api.media_complete(job['job_id'],job['lease_token'],job['completion_key'],result)
    except WorkerFailure as exc:_safe_failure(api,job,exc.code)
    except FanoosApiError as exc:
        mapping={'authorization_changed':'authorization_changed','resource_access_denied':'authorization_changed','input_unavailable':'input_unavailable','input_too_large':'input_too_large'}
        if exc.status<500 and exc.code in mapping:_safe_failure(api,job,mapping[exc.code])
        else:raise
    except Exception:_safe_failure(api,job,'internal_error')
    return True

def main():
    logging.basicConfig(level=os.getenv('LOG_LEVEL','INFO'),format='%(asctime)s %(levelname)s %(message)s')
    api,processor=build()
    while True:
        try:
            if not run_once(api,processor):time.sleep(1)
        except FanoosApiError as exc:
            logging.warning('protected-media backend failure code=%s status=%s',exc.code,exc.status);time.sleep(2)
        except Exception as exc:
            logging.error('protected-media iteration failed type=%s',type(exc).__name__);time.sleep(2)
if __name__=='__main__':main()
