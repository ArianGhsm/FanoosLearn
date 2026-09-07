#!/usr/bin/env python3
import os
from runtime import build
state,backend,transport,_,_=build(); me=transport.get_me(); print('getMe',bool(me and me.get('id')))
chat=os.getenv('FANOOS_SMOKE_CHAT_ID','').strip()
if chat:
    sent=transport.send_screen(chat,__import__('fanoos_bot').Screen('FANOOS Telegram smoke')); print('sendMessage',bool(sent));
state.close()
