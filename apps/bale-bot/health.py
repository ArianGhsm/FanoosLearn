#!/usr/bin/env python3
from runtime import build
try:
    state,backend,transport,_,_=build(); me=transport.get_me(); assert isinstance(me,dict) and me.get('id'); print('OK bale bot identity/backend config/state; forward protection unsupported');state.close()
except Exception as exc:print('FAIL bale health',type(exc).__name__);raise SystemExit(1)
