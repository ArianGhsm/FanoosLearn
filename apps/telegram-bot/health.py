#!/usr/bin/env python3
from runtime import build
try:
    state,backend,transport,_,_=build(); me=transport.get_me(); assert isinstance(me,dict) and me.get('id'); print('OK telegram bot identity/backend config/state'); state.close()
except Exception as exc:
    print('FAIL telegram health',type(exc).__name__); raise SystemExit(1)
