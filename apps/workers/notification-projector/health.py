#!/usr/bin/env python3
from runtime import build
try:build();print('OK notification projector configuration')
except Exception as exc:print('FAIL notification projector health',type(exc).__name__);raise SystemExit(1)
