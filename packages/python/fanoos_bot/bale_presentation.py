from __future__ import annotations

from dataclasses import dataclass
from typing import Any


@dataclass(frozen=True)
class BaleRenderedScreen:
    text: str


def _metadata(screen: Any) -> Any:
    for name in ("presentation", "presentation_metadata", "semantic"):
        value = getattr(screen, name, None)
        if value is not None:
            return value
    value = getattr(screen, "semantic_blocks", None)
    if value is not None:
        return {"blocks": value}
    return None


def _mapping(value: Any) -> dict[str, Any] | None:
    if isinstance(value, dict):
        return value
    blocks = getattr(value, "blocks", None)
    if blocks is None:
        return None
    return {"blocks": blocks}


def _block(value: Any) -> dict[str, Any] | None:
    if isinstance(value, dict):
        return value
    kind = getattr(value, "kind", getattr(value, "type", None))
    if kind is None:
        return None
    out = {"kind": kind}
    for key in ("text", "items", "headers", "rows", "caption"):
        field = getattr(value, key, None)
        if field is not None:
            out[key] = field
    return out


def _semantic_plain(metadata: Any) -> str:
    mapping = _mapping(metadata)
    if mapping is None:
        raise ValueError("semantic metadata is malformed")
    blocks = mapping.get("blocks")
    if not isinstance(blocks, (list, tuple)) or not blocks:
        raise ValueError("semantic blocks are malformed")
    lines: list[str] = []
    for raw in blocks:
        block = _block(raw)
        if block is None:
            raise ValueError("semantic block is malformed")
        kind_value = block.get("kind") or block.get("type") or ""
        kind = str(getattr(kind_value, "value", kind_value)).strip().lower()
        text = str(block.get("text") or "").strip()
        if kind in {"heading", "title", "section_heading", "paragraph", "text"}:
            if not text:
                raise ValueError("empty text block")
            lines.append(text)
        elif kind in {"quote", "warning", "success", "error", "info"}:
            if not text:
                raise ValueError("empty status block")
            lines.append(text)
        elif kind in {"divider", "separator"}:
            lines.append("—")
        elif kind in {"list", "bullets", "bullet_list"}:
            items = block.get("items")
            if not isinstance(items, (list, tuple)) or not items:
                raise ValueError("malformed list")
            lines.extend(f"• {str(item)}" for item in items)
        elif kind == "table":
            headers = block.get("headers")
            rows = block.get("rows")
            if not isinstance(headers, (list, tuple)) or not headers:
                raise ValueError("table headers missing")
            if not isinstance(rows, (list, tuple)) or not rows:
                raise ValueError("table rows missing")
            if block.get("caption"):
                lines.append(str(block["caption"]))
            width = len(headers)
            for row in rows:
                if not isinstance(row, (list, tuple)) or len(row) != width:
                    raise ValueError("malformed table row")
                fields = [f"{headers[index]}: {row[index]}" for index in range(width)]
                lines.append("• " + " · ".join(fields))
        else:
            raise ValueError("unsupported semantic block")
    return "\n\n".join(lines)


class BalePresentation:
    """Bale uses only capabilities documented by Bale's official Bot API."""

    def render(self, screen: Any) -> BaleRenderedScreen:
        text = str(getattr(screen, "text", ""))
        if "\x00" in text:
            raise ValueError("invalid message text")
        normalized = text.replace("\r\n", "\n").replace("\r", "\n")
        metadata = _metadata(screen)
        if metadata is None:
            return BaleRenderedScreen(normalized)
        try:
            semantic = _semantic_plain(metadata)
        except (TypeError, ValueError):
            return BaleRenderedScreen(normalized)
        return BaleRenderedScreen(semantic or normalized)
