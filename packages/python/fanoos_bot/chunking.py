from __future__ import annotations
import unicodedata

def chunks(text: str, limit: int = 4096) -> list[str]:
    if limit < 32: raise ValueError('limit too small')
    text = str(text)
    if not text: return ['']
    out: list[str] = []
    while len(text) > limit:
        cut = max(text.rfind('\n\n', 0, limit + 1), text.rfind('\n', 0, limit + 1), text.rfind(' ', 0, limit + 1))
        if cut < limit // 3: cut = limit
        while cut > 1 and unicodedata.combining(text[cut]): cut -= 1
        out.append(text[:cut].rstrip())
        text = text[cut:].lstrip()
    if text or not out: out.append(text)
    return out
