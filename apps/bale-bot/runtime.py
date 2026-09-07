#!/usr/bin/env python3
from __future__ import annotations
import logging,os,sys,time
from pathlib import Path
ROOT=Path(__file__).resolve().parents[2];sys.path.insert(0,str(ROOT/'packages/python'))
from fanoos_bot.api import FanoosApiClient
from fanoos_bot.application import ApplicationConfig,BotApplication
from fanoos_bot.botapi import JsonBotApiTransport,BotApiError
from fanoos_bot.capabilities import BALE
from fanoos_bot.runtime import BotRuntime,NotificationPump,UpdateContext
from fanoos_bot.state import LocalState
class BaleTransport(JsonBotApiTransport):
    def __init__(self,token):super().__init__('https://tapi.bale.ai',token,BALE)
def required(n):
    v=os.getenv(n,'').strip()
    if not v:raise RuntimeError(f'{n} is required')
    return v
def build():
    state=LocalState(os.getenv('FANOOS_BALE_STATE','/var/lib/fanoos/bale/state.sqlite3'));backend=FanoosApiClient(required('FANOOS_API_ORIGIN'),required('FANOOS_BALE_SERVICE_KEY_ID'),required('FANOOS_BALE_SERVICE_SECRET'));app=BotApplication(backend,state,'bale',ApplicationConfig(os.getenv('FANOOS_WEB_BASE_URL','').strip()));transport=BaleTransport(required('FANOOS_BALE_BOT_TOKEN'));return state,backend,transport,BotRuntime('bale',transport,app,state),NotificationPump('bale',backend,transport,state)
def context(update):
    uid=str(update.get('update_id',''))
    if isinstance(update.get('message'),dict):
        m=update['message'];chat=m.get('chat') or {};user=m.get('from') or {};return UpdateContext(str(user.get('id','')),str(chat.get('id','')),chat.get('type')=='private',m.get('message_id'),None,uid),str(m.get('text') or ''),None
    if isinstance(update.get('callback_query'),dict):
        q=update['callback_query'];user=q.get('from') or {};m=q.get('message') or {};chat=m.get('chat') or {};return UpdateContext(str(user.get('id','')),str(chat.get('id','')),chat.get('type')=='private',m.get('message_id'),str(q.get('id') or ''),uid),'',str(q.get('data') or '')
    return None
def main():
    logging.basicConfig(level=os.getenv('LOG_LEVEL','INFO'),format='%(asctime)s %(levelname)s %(message)s');state,backend,transport,runtime,pump=build();offset=state.get_offset('bale')
    try:
        while True:
            try:
                for update in transport.get_updates(offset,20) or []:
                    parsed=context(update)
                    if parsed:
                        ctx,text,cb=parsed
                        if cb is not None:runtime.handle_callback(ctx,cb)
                        elif text:runtime.handle_message(ctx,text)
                    offset=max(offset,int(update.get('update_id',0))+1);state.set_offset('bale',offset)
                for _ in range(5):
                    if not pump.run_once():break
            except BotApiError as exc:logging.warning('bale transport failure code=%s transient=%s',exc.code,exc.transient);time.sleep(min(10,max(1,exc.retry_after or 2)))
            except Exception as exc:logging.error('bale runtime iteration failed type=%s',type(exc).__name__);time.sleep(2)
    finally:state.close()
if __name__=='__main__':main()
