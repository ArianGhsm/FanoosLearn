from __future__ import annotations
import re

_CALLBACK = re.compile(r'^[A-Za-z0-9._:-]{1,64}$')
_ACTION = re.compile(r'^[a-z][a-z0-9_]{0,11}$')

class CallbackError(ValueError): pass

class CallbackCodec:
    @staticmethod
    def encode(action: str, ref: str | None = None) -> str:
        if not _ACTION.fullmatch(action): raise CallbackError('invalid callback action')
        value = action if ref in (None, '') else f'{action}:{ref}'
        if len(value.encode('utf-8')) > 64 or not _CALLBACK.fullmatch(value):
            raise CallbackError('callback exceeds platform contract')
        return value
    @staticmethod
    def decode(value: str) -> tuple[str, str | None]:
        if len(value.encode('utf-8')) > 64 or not _CALLBACK.fullmatch(value):
            raise CallbackError('invalid callback')
        action, sep, ref = value.partition(':')
        if not _ACTION.fullmatch(action): raise CallbackError('invalid callback action')
        return action, ref if sep else None
