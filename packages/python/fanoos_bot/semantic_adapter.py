from __future__ import annotations

from typing import Any


def _value(source: Any, name: str, default: Any = None) -> Any:
    if isinstance(source, dict):
        return source.get(name, default)
    return getattr(source, name, default)


def _section_value(section: Any, name: str, default: Any = None) -> Any:
    if isinstance(section, dict):
        return section.get(name, default)
    return getattr(section, name, default)


def semantic_mapping(metadata: Any) -> dict[str, Any] | None:
    """Return transport-neutral presentation blocks.

    Presentation metadata is deliberately non-authoritative: it can describe
    context, severity and pagination, but it cannot carry a permission grant,
    payment result or entitlement decision.
    """
    if metadata is None:
        return None

    if isinstance(metadata, dict) and isinstance(metadata.get("blocks"), (list, tuple)):
        return {
            "blocks": list(metadata["blocks"]),
            "rtl": bool(metadata.get("rtl", True)),
        }

    blocks_attr = getattr(metadata, "blocks", None)
    if isinstance(blocks_attr, (list, tuple)):
        return {
            "blocks": list(blocks_attr),
            "rtl": bool(getattr(metadata, "rtl", True)),
        }

    title = _value(metadata, "title", "")
    semantic_kind = _value(metadata, "semantic_kind", None)
    if not title and semantic_kind is None:
        return None

    severity = str(_value(metadata, "severity", "info") or "info").lower()
    breadcrumb = _value(metadata, "breadcrumb", "")
    intro = _value(metadata, "intro", "")
    facts = _value(metadata, "facts", ()) or ()
    list_items = _value(metadata, "list_items", ()) or ()
    sections = _value(metadata, "sections", ()) or ()
    pagination = _value(metadata, "pagination", "")
    footer = _value(metadata, "footer", "")
    rtl = bool(_value(metadata, "rtl", True))

    blocks: list[dict[str, Any]] = []
    if title:
        blocks.append({"kind": "heading", "text": str(title), "level": 3})

    if breadcrumb:
        blocks.append({"kind": "paragraph", "text": str(breadcrumb)})

    if intro:
        kind = severity if severity in {"warning", "success", "error"} else "paragraph"
        blocks.append({"kind": kind, "text": str(intro)})

    fact_items: list[str] = []
    for fact in facts:
        if isinstance(fact, dict):
            label = fact.get("label", "")
            value = fact.get("value", "")
        elif isinstance(fact, (list, tuple)) and len(fact) == 2:
            label, value = fact
        else:
            continue
        if label or value:
            fact_items.append(f"{label}: {value}" if label else str(value))
    if fact_items:
        blocks.append({"kind": "list", "items": fact_items})

    normalized_items = [str(item) for item in list_items if str(item)]
    if normalized_items:
        blocks.append({"kind": "list", "items": normalized_items})

    for section in sections:
        section_title = _section_value(section, "title", "")
        section_body = _section_value(section, "body", "")
        section_items = _section_value(section, "items", ()) or ()
        if section_title:
            blocks.append({"kind": "section_heading", "text": str(section_title), "level": 4})
        if section_body:
            blocks.append({"kind": "paragraph", "text": str(section_body)})
        normalized_section_items = [str(item) for item in section_items if str(item)]
        if normalized_section_items:
            blocks.append({"kind": "list", "items": normalized_section_items})

    if pagination:
        blocks.append({"kind": "paragraph", "text": str(pagination)})

    if footer:
        blocks.append({"kind": "paragraph", "text": str(footer)})

    if not blocks:
        return None
    return {"blocks": blocks, "rtl": rtl}
