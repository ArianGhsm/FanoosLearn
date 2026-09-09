"""Bounded filter pickers for V3 learning destinations.

Filter choices are rendered only from canonical options supplied by integration.
IDs/slugs remain callback correlation and are never visible product copy.
"""

from __future__ import annotations

from collections.abc import Mapping, Sequence
from typing import Any

from ..core import Action, ActionRow, Breadcrumb, CallbackIntent, Screen, Section
from .intents import LearningIntent


def _clean(value: Any, limit: int = 48) -> str:
    text = " ".join(str(value or "").split())
    return text if len(text) <= limit else text[: max(1, limit - 1)].rstrip() + "…"


def _action(label: str, intent: LearningIntent, *, key: str | None = None, value: Any = None) -> Action:
    params = ()
    rendered = str(value or "").strip()
    if key and rendered:
        params = ((key, rendered[:256]),)
    name = str(intent)
    return Action(identifier=name, label=label, intent=CallbackIntent(name=name, params=params))


def _rows(actions: Sequence[Action]) -> tuple[ActionRow, ...]:
    return tuple(ActionRow(tuple(actions[index : index + 2])) for index in range(0, len(actions), 2))


def _exits(back: LearningIntent) -> tuple[ActionRow, ...]:
    return (
        ActionRow(
            (
                _action("‹ بازگشت", back),
                _action("🏠 خانه", LearningIntent.HOME),
            )
        ),
    )


def resource_course_filter_screen(courses: Sequence[Mapping[str, Any]]) -> Screen:
    actions: list[Action] = []
    for course in courses[:12]:
        course_id = str(course.get("course_id") or course.get("id") or "")
        label = _clean(course.get("course_title") or course.get("title") or "درس")
        if course_id:
            actions.append(_action(label, LearningIntent.RESOURCES_APPLY_COURSE, key="course", value=course_id))
    section = Section(
        title="انتخاب درس",
        body="درسی برای فیلتر منابع پیدا نشد." if not actions else "یک درس را انتخاب کنید.",
    )
    return Screen(
        identifier="learning.resource_filter_course",
        title="📚 فیلتر منابع بر اساس درس",
        breadcrumb=(Breadcrumb("منابع"), Breadcrumb("فیلتر درس")),
        sections=(section,),
        action_rows=_rows(actions[:8]) + _exits(LearningIntent.RESOURCES_OPEN),
    )


def resource_type_filter_screen(types: Sequence[Mapping[str, Any] | str]) -> Screen:
    actions: list[Action] = []
    for option in types[:12]:
        if isinstance(option, Mapping):
            value = str(option.get("type_key") or option.get("value") or "")
            label = _clean(option.get("label") or option.get("title") or value.replace("_", " "))
        else:
            value = str(option)
            label = _clean(value.replace("_", " "))
        if value and label:
            actions.append(_action(label, LearningIntent.RESOURCES_APPLY_TYPE, key="type", value=value))
    section = Section(
        title="انتخاب نوع",
        body="نوع منبعی برای فیلتر پیدا نشد." if not actions else "نوع منبع را انتخاب کنید.",
    )
    return Screen(
        identifier="learning.resource_filter_type",
        title="📚 فیلتر منابع بر اساس نوع",
        breadcrumb=(Breadcrumb("منابع"), Breadcrumb("فیلتر نوع")),
        sections=(section,),
        action_rows=_rows(actions[:8]) + _exits(LearningIntent.RESOURCES_OPEN),
    )


def assessment_course_filter_screen(courses: Sequence[Mapping[str, Any]]) -> Screen:
    actions: list[Action] = []
    for course in courses[:12]:
        course_id = str(course.get("course_id") or course.get("id") or "")
        label = _clean(course.get("course_title") or course.get("title") or "درس")
        if course_id:
            actions.append(_action(label, LearningIntent.ASSESSMENTS_APPLY_COURSE, key="course", value=course_id))
    return Screen(
        identifier="learning.assessment_filter_course",
        title="📝 فیلتر آزمون‌ها بر اساس درس",
        breadcrumb=(Breadcrumb("آزمون‌ها"), Breadcrumb("فیلتر درس")),
        sections=(Section(title="انتخاب درس", body="یک درس را انتخاب کنید." if actions else "درسی برای انتخاب وجود ندارد."),),
        action_rows=_rows(actions[:8]) + _exits(LearningIntent.ASSESSMENTS_OPEN),
    )


def assessment_state_filter_screen(states: Sequence[Mapping[str, Any] | str]) -> Screen:
    actions: list[Action] = []
    for option in states[:8]:
        if isinstance(option, Mapping):
            value = str(option.get("state") or option.get("value") or "")
            label = _clean(option.get("label") or option.get("title") or value)
        else:
            value = str(option)
            label = _clean(value)
        if value and label:
            actions.append(_action(label, LearningIntent.ASSESSMENTS_APPLY_STATE, key="state", value=value))
    return Screen(
        identifier="learning.assessment_filter_state",
        title="📝 فیلتر آزمون‌ها بر اساس وضعیت",
        breadcrumb=(Breadcrumb("آزمون‌ها"), Breadcrumb("فیلتر وضعیت")),
        sections=(Section(title="انتخاب وضعیت", body="یک وضعیت را انتخاب کنید." if actions else "وضعیتی برای انتخاب وجود ندارد."),),
        action_rows=_rows(actions) + _exits(LearningIntent.ASSESSMENTS_OPEN),
    )
