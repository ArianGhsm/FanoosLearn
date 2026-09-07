#!/usr/bin/env python3
from runtime import build
state,backend,transport,_,_=build();me=transport.get_me();print('getMe',bool(me and me.get('id')));print('protect_content',False);state.close()
