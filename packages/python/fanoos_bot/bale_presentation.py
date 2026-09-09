from __future__ import annotations

from dataclasses import dataclass
from typing import Any

from .semantic_adapter import semantic_mapping


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


def _semantic_kind(metadata: Any) -> str:
    if isinstance(metadata, dict):
        return str(metadata.get("semantic_kind") or "")
    return str(getattr(metadata, "semantic_kind", "") or "")


def _mapping(value: Any) -> dict[str, Any] | None:
    return semantic_mapping(value)


def _block(value: Any) -> dict[str, Any] | None:
    if isinstance(value, dict):
        return value
    if value is None:
        return None
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

    groups: list[str] = []
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
            groups.append(text)
        elif kind in {"quote", "warning", "success", "error", "info"}:
            if not text:
                raise ValueError("empty status block")
            groups.append(text)
        elif kind in {"divider", "separator"}:
            groups.append("—")
        elif kind in {"list", "bullets", "bullet_list"}:
            items = block.get("items")
            if not isinstance(items, (list, tuple)) or not items:
                raise ValueError("malformed list")
            groups.append("\n".join(f"• {str(item)}" for item in items))
        elif kind == "table":
            headers = block.get("headers")
            rows = block.get("rows")
            if not isinstance(headers, (list, tuple)) or not headers:
                raise ValueError("table headers missing")
            if not isinstance(rows, (list, tuple)) or not rows:
                raise ValueError("table rows missing")
            table_lines: list[str] = []
            if block.get("caption"):
                table_lines.append(str(block["caption"]))
            width = len(headers)
            for row in rows:
                if not isinstance(row, (list, tuple)) or len(row) != width:
                    raise ValueError("malformed table row")
                fields = [
                    f"{headers[index]}: {row[index]}"
                    for index in range(width)
                ]
                table_lines.append("• " + " · ".join(fields))
            groups.append("\n".join(table_lines))
        else:
            raise ValueError("unsupported semantic block")
    return "\n\n".join(group for group in groups if group)


class BalePresentation:
    """Readable semantic rendering using only the project's verified Bale surface.

    Telegram Rich payloads are never forwarded to Bale. Inline-keyboard/edit
    support remains transport capability-driven, not guessed here.
    """

    def render(self, screen: Any) -> BaleRenderedScreen:
        text = str(getattr(screen, "text", ""))
        if "\x00" in text:
            raise ValueError("invalid message text")
        normalized = text.replace("\r\n", "\n").replace("\r", "\n")
        metadata = _metadata(screen)
        # Authorized protected payloads must remain byte-for-byte presentation
        # equivalents; replacing them with a semantic summary could hide the
        # actual delivery content or accidentally trigger a second operation.
        if bool(getattr(screen, "protect_content", False)) or _semantic_kind(metadata) == "protected_delivery_ready":
            return BaleRenderedScreen(normalized)
        if metadata is None:
            return BaleRenderedScreen(normalized)
        try:
            semantic = _semantic_plain(metadata)
        except (TypeError, ValueError):
            return BaleRenderedScreen(normalized)
        return BaleRenderedScreen(semantic or normalized)
