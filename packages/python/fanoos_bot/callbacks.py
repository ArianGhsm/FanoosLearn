from __future__ import annotations

import re

_CALLBACK_RE = re.compile(r"^[A-Za-z0-9._:-]{1,64}$")


class CallbackCodec:
    MAX_BYTES = 64

    @classmethod
    def encode(cls, action: str, ref: str | None = None) -> str:
        if not re.fullmatch(r"[a-z][a-z0-9_]{0,11}", action):
            raise ValueError("Invalid callback action")
        data = action if ref is None else f"{action}:{ref}"
        cls.validate(data)
        return data

    @classmethod
    def validate(cls, data: str) -> str:
        if not _CALLBACK_RE.fullmatch(data) or len(data.encode("utf-8")) > cls.MAX_BYTES:
            raise ValueError("Invalid or oversized callback data")
        return data

    @classmethod
    def decode(cls, data: str) -> tuple[str, str | None]:
        cls.validate(data)
        if ":" not in data:
            return data, None
        action, ref = data.split(":", 1)
        if not action or not ref:
            raise ValueError("Malformed callback data")
        return action, ref
