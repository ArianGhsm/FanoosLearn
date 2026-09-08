from __future__ import annotations

import html
import os
from dataclasses import dataclass
from typing import Any, Iterable

from .semantic_adapter import semantic_mapping


_TITLE_TERMINATORS = (".", "!", "?", "؟", "؛", "،", ":")
_SEVERITY_PREFIXES = ("⚠️", "❌", "✅", "ℹ️")
_DIVIDERS = {"---", "—", "——", "———"}


@dataclass(frozen=True)
class TelegramRenderedScreen:
    plain_text: str
    rich_message: dict[str, Any] | None


def _env_flag(name: str, default: bool) -> bool:
    raw = os.getenv(name)
    if raw is None:
        return default
    value = raw.strip().lower()
    if value in {"1", "true", "yes", "on"}:
        return True
    if value in {"0", "false", "no", "off"}:
        return False
    return default


def rich_ui_enabled_from_env() -> bool:
    return _env_flag("FANOOS_TELEGRAM_RICH_UI_ENABLED", True)


def _metadata(screen: Any) -> Any:
    for name in ("presentation", "presentation_metadata", "semantic"):
        value = getattr(screen, name, None)
        if value is not None:
            return value
    value = getattr(screen, "semantic_blocks", None)
    if value is not None:
        return {"blocks": value}
    return None


def _as_mapping(value: Any) -> dict[str, Any] | None:
    return semantic_mapping(value)


def _block_mapping(value: Any) -> dict[str, Any] | None:
    if isinstance(value, dict):
        return value
    if value is None:
        return None
    kind = getattr(value, "kind", getattr(value, "type", None))
    if kind is None:
        return None
    out: dict[str, Any] = {"kind": kind}
    for key in ("text", "items", "headers", "rows", "caption", "severity", "level"):
        field = getattr(value, key, None)
        if field is not None:
            out[key] = field
    return out


def _escape(value: Any) -> str:
    return html.escape(str(value), quote=True)


def _heading(text: str, level: int = 3) -> str:
    level = min(6, max(1, int(level)))
    return f"<h{level}>{_escape(text)}</h{level}>"


def _paragraph(text: str) -> str:
    return f"<p>{_escape(text)}</p>"


def _quotation(text: str) -> str:
    return f"<blockquote>{_escape(text)}</blockquote>"


def _list(items: Iterable[Any]) -> str:
    rendered = "".join(f"<li>{_escape(item)}</li>" for item in items)
    if not rendered:
        raise ValueError("empty rich list")
    return f"<ul>{rendered}</ul>"


def _table(block: dict[str, Any], max_columns: int) -> str:
    headers = block.get("headers")
    rows = block.get("rows")
    if not isinstance(headers, (list, tuple)) or not headers:
        raise ValueError("table headers missing")
    if len(headers) > max_columns:
        raise ValueError("too many table columns")
    if not isinstance(rows, (list, tuple)) or not rows:
        raise ValueError("table rows missing")
    width = len(headers)
    normalized_rows: list[list[Any]] = []
    for row in rows:
        if not isinstance(row, (list, tuple)) or len(row) != width:
            raise ValueError("malformed table row")
        normalized_rows.append(list(row))
    parts: list[str] = ["<table bordered striped compact>"]
    caption = block.get("caption")
    if caption not in (None, ""):
        parts.append(f"<caption>{_escape(caption)}</caption>")
    parts.append("<tr>" + "".join(f"<th>{_escape(cell)}</th>" for cell in headers) + "</tr>")
    for row in normalized_rows:
        parts.append("<tr>" + "".join(f"<td>{_escape(cell)}</td>" for cell in row) + "</tr>")
    parts.append("</table>")
    return "".join(parts)


def _metadata_html(metadata: Any, *, max_columns: int, max_blocks: int) -> str:
    mapping = _as_mapping(metadata)
    if mapping is None:
        raise ValueError("semantic metadata is malformed")
    blocks = mapping.get("blocks")
    if not isinstance(blocks, (list, tuple)) or not blocks or len(blocks) > max_blocks:
        raise ValueError("semantic blocks are malformed")
    out: list[str] = []
    for raw in blocks:
        block = _block_mapping(raw)
        if block is None:
            raise ValueError("semantic block is malformed")
        kind_value = block.get("kind") or block.get("type") or ""
        kind = str(getattr(kind_value, "value", kind_value)).strip().lower()
        text = str(block.get("text") or "")
        if kind in {"heading", "title", "section_heading"}:
            if not text:
                raise ValueError("empty heading")
            out.append(_heading(text, int(block.get("level") or 3)))
        elif kind in {"paragraph", "text"}:
            if not text:
                raise ValueError("empty paragraph")
            out.append(_paragraph(text))
        elif kind in {"list", "bullets", "bullet_list"}:
            items = block.get("items")
            if not isinstance(items, (list, tuple)):
                raise ValueError("malformed list")
            out.append(_list(items))
        elif kind in {"divider", "separator"}:
            out.append("<hr>")
        elif kind in {"quote", "warning", "success", "error", "info"}:
            if not text:
                raise ValueError("empty quotation")
            out.append(_quotation(text))
        elif kind == "table":
            out.append(_table(block, max_columns))
        else:
            raise ValueError("unsupported semantic block")
    return "".join(out)


def _looks_like_heading(text: str) -> bool:
    value = text.strip()
    if not value or value.startswith("•") or value.startswith(_SEVERITY_PREFIXES):
        return False
    if len(value) > 72 or len(value.split()) > 10:
        return False
    if value.endswith(_TITLE_TERMINATORS):
        return False
    return "\n" not in value


def _heuristic_html(text: str, *, max_blocks: int) -> str:
    if "\x00" in text:
        raise ValueError("invalid text")
    lines = text.splitlines()
    nonempty = [index for index, line in enumerate(lines) if line.strip()]
    if not nonempty:
        return "<p></p>"
    first = nonempty[0]
    out: list[str] = []
    block_count = 0
    index = 0
    while index < len(lines):
        value = lines[index].strip()
        if not value:
            index += 1
            continue
        if block_count >= max_blocks:
            raise ValueError("too many heuristic blocks")
        if index == first and _looks_like_heading(value):
            out.append(_heading(value))
            block_count += 1
            index += 1
            continue
        if value in _DIVIDERS:
            out.append("<hr>")
            block_count += 1
            index += 1
            continue
        if value.startswith("•"):
            items: list[str] = []
            while index < len(lines):
                candidate = lines[index].strip()
                if not candidate.startswith("•"):
                    break
                item = candidate[1:].strip()
                if not item:
                    raise ValueError("empty bullet")
                items.append(item)
                index += 1
            out.append(_list(items))
            block_count += 1
            continue
        if value.startswith(_SEVERITY_PREFIXES):
            out.append(_quotation(value))
        else:
            out.append(_paragraph(value))
        block_count += 1
        index += 1
    return "".join(out)


class TelegramPresentation:
    def __init__(
        self,
        *,
        enabled: bool | None = None,
        max_rich_chars: int = 32_768,
        max_blocks: int = 500,
        max_table_columns: int = 20,
    ):
        self.enabled = rich_ui_enabled_from_env() if enabled is None else bool(enabled)
        self.max_rich_chars = max_rich_chars
        self.max_blocks = max_blocks
        self.max_table_columns = max_table_columns

    def render(self, screen: Any) -> TelegramRenderedScreen:
        plain = str(getattr(screen, "text", ""))
        # Protected delivery must be one exact provider operation. Rich-to-plain
        # fallback after an ambiguous transport failure could duplicate content.
        if bool(getattr(screen, "protect_content", False)):
            return TelegramRenderedScreen(plain, None)
        if not self.enabled or len(plain) > self.max_rich_chars:
            return TelegramRenderedScreen(plain, None)
        metadata = _metadata(screen)
        mapping = _as_mapping(metadata) if metadata is not None else None
        # Metadata is authoritative when present. Malformed/unsupported metadata
        # falls back to Screen.text rather than silently re-interpreting it.
        if metadata is not None and mapping is None:
            return TelegramRenderedScreen(plain, None)
        try:
            rich_html = (
                _metadata_html(
                    mapping,
                    max_columns=self.max_table_columns,
                    max_blocks=self.max_blocks,
                )
                if mapping is not None
                else _heuristic_html(plain, max_blocks=self.max_blocks)
            )
        except (TypeError, ValueError):
            return TelegramRenderedScreen(plain, None)
        if not rich_html:
            return TelegramRenderedScreen(plain, None)
        return TelegramRenderedScreen(
            plain,
            {
                "html": rich_html,
                "is_rtl": bool(mapping.get("rtl", True)) if mapping is not None else True,
            },
        )
