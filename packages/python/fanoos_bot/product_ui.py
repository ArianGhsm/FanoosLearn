from __future__ import annotations

from collections.abc import Iterable

from .formatting import is_uuid, truncate_text


def course_catalog(*collections: Iterable[dict]) -> tuple[dict, ...]:
    """Build a presentation-only course index from already authorized projections.

    The backend remains authoritative. The result is a convenience index and
    must be rebuilt/rechecked before course-sensitive actions.
    """
    by_id: dict[str, dict] = {}
    for collection in collections:
        for item in collection:
            if not isinstance(item, dict):
                continue
            course_id = str(item.get("course_id") or "")
            if not is_uuid(course_id):
                continue
            title = " ".join(str(item.get("course_title") or "").split())
            if not title:
                continue
            current = by_id.get(course_id)
            code = " ".join(str(item.get("course_code") or "").split())
            if current is None:
                by_id[course_id] = {
                    "course_id": course_id,
                    "title": truncate_text(title, 90),
                    "code": truncate_text(code, 30),
                }
            elif not current.get("code") and code:
                current["code"] = truncate_text(code, 30)
    return tuple(sorted(by_id.values(), key=lambda row: (row["title"], row["course_id"])))


def filter_course(items: Iterable[dict], course_id: str) -> tuple[dict, ...]:
    if not is_uuid(course_id):
        return ()
    return tuple(
        item
        for item in items
        if isinstance(item, dict) and str(item.get("course_id") or "") == course_id
    )


def find_course(courses: Iterable[dict], course_id: str) -> dict | None:
    for course in courses:
        if str(course.get("course_id") or "") == course_id:
            return dict(course)
    return None
