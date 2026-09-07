from __future__ import annotations


def chunk_text(text: str, limit: int = 4096) -> list[str]:
    """Split Unicode text without byte slicing, preferring semantic boundaries."""
    if limit < 64:
        raise ValueError("Chunk limit is unrealistically small")
    text = text.strip()
    if not text:
        return [""]
    chunks: list[str] = []
    remaining = text
    boundaries = ("\n\n", "\n", ". ", "؟ ", "! ", "، ", " ")
    while len(remaining) > limit:
        cut = -1
        for boundary in boundaries:
            candidate = remaining.rfind(boundary, 0, limit + 1)
            if candidate > max(0, limit // 3):
                cut = candidate + len(boundary)
                break
        if cut <= 0:
            cut = limit
            while cut > 1 and cut < len(remaining) and _is_combining_like(remaining[cut]):
                cut -= 1
        chunks.append(remaining[:cut].rstrip())
        remaining = remaining[cut:].lstrip()
    if remaining or not chunks:
        chunks.append(remaining)
    return chunks


def _is_combining_like(char: str) -> bool:
    import unicodedata

    return bool(unicodedata.combining(char)) or "VARIATION SELECTOR" in unicodedata.name(char, "")
