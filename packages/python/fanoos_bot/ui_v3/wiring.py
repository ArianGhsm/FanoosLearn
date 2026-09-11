from __future__ import annotations

import re
from dataclasses import replace
from typing import Any, Mapping

from ..models import Button, Screen as RuntimeScreen, ScreenPresentation, SemanticSection
from .core import (
    Action,
    ActionRow,
    Breadcrumb,
    CallbackIntent,
    EditPolicy,
    Fact,
    ListItem,
    Pagination,
    ProtectContent,
    Screen,
    Section,
    Severity,
)

_SAFE_IDENTIFIER = re.compile(r"^[a-z0-9][a-z0-9_.:-]{0,63}$")

# One integration-owned registry. Values are application method keys, not
# permissions. The backend called by each destination remains authoritative.
INTENT_REGISTRY: dict[str, str] = {
    # bot-01/core
    "home": "home",
    "back": "home",
    "cancel": "home",
    "retry": "home",
    "help": "help",
    "more": "more",
    "courses": "courses",
    "schedule": "schedule",
    "grades": "grades",
    "notifications": "notifications",
    "resources": "resources",
    "assessments": "assessments",
    "payments": "payments",
    "ws.list": "workspaces",
    "ws.page": "workspaces_page",
    "ws.select": "workspace_select",
    "account": "account",
    "acct.unlink.ask": "unlink_ask",
    "acct.unlink.do": "unlink_do",
    # bot-02/academic
    "academic.courses.page": "courses_page",
    "academic.course.open": "course_open",
    "academic.course.schedule": "course_schedule",
    "academic.course.resources": "course_resources",
    "academic.course.assessments": "course_assessments",
    "academic.course.grades": "course_grades",
    "academic.course.announcements": "course_announcements",
    "academic.course.sessions": "course_open",
    "academic.schedule.today": "schedule_today",
    "academic.schedule.tomorrow": "schedule_tomorrow",
    "academic.schedule.upcoming": "schedule_upcoming",
    "academic.schedule.page": "schedule_page",
    "academic.schedule.event.open": "schedule_event_open",
    "academic.grades.page": "grades_page",
    "academic.grades.course.open": "course_grades",
    "academic.announcements": "announcements",
    "academic.announcements.page": "announcements_page",
    "academic.announcement.open": "announcements",
    "academic.announcement.link.open": "announcements",
    # bot-03/learning and commerce
    "learning.resources.open": "resources",
    "learning.resources.recent": "resources",
    "learning.resources.filter.course": "resources",
    "learning.resources.filter.type": "resources",
    "learning.resources.apply.course": "resources",
    "learning.resources.apply.type": "resources",
    "learning.resources.page.previous": "resources_page",
    "learning.resources.page.next": "resources_page",
    "learning.resource.open": "resource_open",
    "learning.resource.deliver": "resource_deliver",
    "learning.protected.check": "protected",
    "learning.protected.refresh": "protected",
    "learning.protected.retry": "protected",
    "learning.protected.resource": "resource_open",
    "learning.assessments.open": "assessments",
    "learning.assessments.filter.course": "assessments",
    "learning.assessments.filter.state": "assessments",
    "learning.assessments.apply.course": "assessments",
    "learning.assessments.apply.state": "assessments",
    "learning.assessment.open": "assessments",
    "learning.assessment.open_web": "assessments",
    "learning.commerce.open": "payments",
    "learning.commerce.open_web": "payments",
    "learning.order.open": "payments",
    "learning.order.refresh": "payments",
    "learning.payment.open_web": "payments",
    "learning.payment.retry_web": "payments",
    "learning.forms.open": "forms",
    "learning.form.open": "form_open",
    "learning.form.open_web": "home",
}


def _identifier(value: str, fallback: str) -> str:
    raw = str(value or "").strip().lower().replace("_", ".")
    raw = re.sub(r"[^a-z0-9_.:-]+", ".", raw).strip(".")
    candidate = raw[:64] or fallback
    return candidate if _SAFE_IDENTIFIER.fullmatch(candidate) else fallback


def _severity(value: Any) -> Severity:
    raw = str(getattr(value, "value", value) or "info").lower()
    return {
        "neutral": Severity.NEUTRAL,
        "info": Severity.INFO,
        "success": Severity.SUCCESS,
        "warning": Severity.WARNING,
        "error": Severity.ERROR,
    }.get(raw, Severity.INFO)


def _legacy_action(button: Button, index: int) -> Action:
    if button.url is not None:
        return Action(f"legacy.url.{index}", button.text, url=button.url)
    callback = str(button.callback or "")
    if not callback or len(callback.encode("utf-8")) > 64 or not _SAFE_IDENTIFIER.fullmatch(callback):
        raise ValueError("legacy callback cannot be represented safely in V3")
    return Action(
        f"legacy.callback.{index}",
        button.text,
        intent=CallbackIntent(callback),
    )


def legacy_to_core(screen: RuntimeScreen) -> Screen:
    if isinstance(screen.v3, Screen):
        return screen.v3
    presentation = screen.presentation
    title = presentation.title if presentation else ""
    if not title:
        title = next((line.strip() for line in str(screen.text).splitlines() if line.strip()), "فانوس")
    kind = presentation.semantic_kind if presentation else "screen"
    sections: list[Section] = []
    if presentation:
        facts = tuple(Fact(str(label), str(value)) for label, value in presentation.facts if str(label).strip() and str(value).strip())
        items = tuple(ListItem(str(item)) for item in presentation.list_items[:8] if str(item).strip())
        if facts or items:
            sections.append(Section(facts=facts, items=items))
        for source in presentation.sections:
            if len(sections) >= 3:
                break
            source_items = tuple(ListItem(str(item)) for item in source.items[:8] if str(item).strip())
            if source.title or source.body or source_items:
                sections.append(Section(title=source.title, body=source.body, items=source_items))
    if not presentation and screen.text and screen.text.strip() != title.strip():
        sections.append(Section(body=screen.text.strip()))

    rows: list[ActionRow] = []
    action_index = 0
    for row in screen.rows[:10]:
        actions: list[Action] = []
        for button in row[:2]:
            action_index += 1
            actions.append(_legacy_action(button, action_index))
        if actions:
            rows.append(ActionRow(tuple(actions)))

    crumbs: tuple[Breadcrumb, ...] = ()
    if presentation and presentation.breadcrumb:
        crumbs = tuple(Breadcrumb(part.strip()) for part in presentation.breadcrumb.split("›") if part.strip())

    pagination = None
    if presentation and presentation.pagination:
        pagination = Pagination(page=1, label=presentation.pagination)

    return Screen(
        identifier=_identifier(kind, "legacy.screen"),
        title=title,
        intro=presentation.intro if presentation else "",
        severity=_severity(presentation.severity if presentation else "info"),
        breadcrumb=crumbs,
        sections=tuple(sections[:3]),
        action_rows=tuple(rows),
        pagination=pagination,
        footer=presentation.footer if presentation else "",
        protect_content=ProtectContent.REQUIRED if screen.protect_content else ProtectContent.INHERIT,
        edit_policy=EditPolicy.EDIT_IF_SAFE if screen.edit else EditPolicy.SEND_NEW,
        rtl=presentation.rtl if presentation else True,
    )


def _resolve_intent(app: Any, subject: str, intent: CallbackIntent) -> CallbackIntent:
    if intent.route_ref:
        return intent
    if intent.name not in INTENT_REGISTRY:
        raise ValueError(f"unregistered V3 intent: {intent.name}")
    if intent.compact() is not None:
        return intent
    ref = app.state.create_route(
        app.platform,
        subject,
        "v3_intent",
        {"name": intent.name, "params": dict(intent.params)},
    )
    return CallbackIntent(intent.name, route_ref=ref)


def _resolve_action(app: Any, subject: str, action: Action) -> Action:
    if action.intent is None:
        return action
    return replace(action, intent=_resolve_intent(app, subject, action.intent))


def resolve_screen_intents(app: Any, subject: str, screen: Screen) -> Screen:
    rows = tuple(
        ActionRow(tuple(_resolve_action(app, subject, action) for action in row.actions))
        for row in screen.action_rows
    )
    pagination = screen.pagination
    if pagination:
        pagination = replace(
            pagination,
            previous=_resolve_action(app, subject, pagination.previous) if pagination.previous else None,
            next=_resolve_action(app, subject, pagination.next) if pagination.next else None,
        )
    return replace(screen, action_rows=rows, pagination=pagination)


def core_to_runtime(app: Any, subject: str, screen: Screen) -> RuntimeScreen:
    resolved = resolve_screen_intents(app, subject, screen)
    rows: list[tuple[Button, ...]] = []
    for row in resolved.action_rows:
        rendered: list[Button] = []
        for action in row.actions:
            if action.url is not None:
                rendered.append(Button(action.label, url=action.url))
            else:
                callback = action.intent.compact() if action.intent else None
                if callback is None:
                    raise ValueError("unresolved V3 action")
                rendered.append(Button(action.label, callback=callback))
        rows.append(tuple(rendered))
    if resolved.pagination:
        pager: list[Button] = []
        for action in (resolved.pagination.previous, resolved.pagination.next):
            if action and action.intent:
                callback = action.intent.compact()
                if callback:
                    pager.append(Button(action.label, callback=callback))
        if pager:
            rows.append(tuple(pager))

    semantic_sections = tuple(
        SemanticSection(
            section.title,
            section.body,
            tuple(
                [f"{fact.label}: {fact.value}" for fact in section.facts]
                + [
                    " · ".join(part for part in (item.marker, item.title, item.description, item.meta) if part)
                    for item in section.items
                ]
            ),
        )
        for section in resolved.sections
    )
    presentation = ScreenPresentation(
        title=resolved.title,
        semantic_kind=resolved.identifier,
        severity=resolved.severity.value,
        breadcrumb=" › ".join(crumb.label for crumb in resolved.breadcrumb),
        intro=resolved.intro,
        sections=semantic_sections,
        pagination=resolved.pagination.label if resolved.pagination else "",
        footer=resolved.footer,
        rtl=resolved.rtl,
    )
    return RuntimeScreen(
        text=resolved.plain_text(),
        rows=tuple(rows[:10]),
        edit=resolved.edit_policy is EditPolicy.EDIT_IF_SAFE,
        protect_content=resolved.protect_content is ProtectContent.REQUIRED,
        presentation=presentation,
        v3=resolved,
    )


def _params_from_value(value: str) -> tuple[str, dict[str, str]] | None:
    name, separator, raw_params = value.partition("|")
    if name not in INTENT_REGISTRY:
        return None
    params: dict[str, str] = {}
    if separator:
        for pair in raw_params.split("&"):
            key, equals, raw = pair.partition("=")
            if not equals or not key or not raw or key in params:
                return None
            params[key] = raw
    return name, params


def decode_v3_intent(app: Any, subject: str, value: str) -> tuple[str, dict[str, str]] | None:
    if value.startswith("r:"):
        ref = value[2:]
        row = app.state.route(ref, app.platform, subject)
        if not row or row.get("kind") != "v3_intent":
            return None
        payload = row.get("payload") or {}
        name = str(payload.get("name") or "")
        params = payload.get("params") or {}
        if name not in INTENT_REGISTRY or not isinstance(params, dict):
            return None
        return name, {str(key): str(raw) for key, raw in params.items()}
    return _params_from_value(value)


def dispatch_v3_intent(app: Any, subject: str, private: bool, name: str, params: Mapping[str, str]):
    target = INTENT_REGISTRY.get(name)
    if target is None:
        return app._expired_route()
    course = str(params.get("course_id") or params.get("course") or "")
    resource = str(params.get("resource") or "")
    workspace = str(params.get("w") or params.get("workspace_id") or "")
    page = str(params.get("p") or params.get("page") or "")
    job = str(params.get("job") or "")

    if target == "home": return app.home(subject)
    if target == "help": return app.help()
    if target == "more": return app.more(subject, private)
    if target == "courses": return app.courses(subject)
    if target == "schedule": return app.schedule_menu(subject)
    if target == "grades": return app.grades(subject)
    if target == "notifications": return app.notifications(subject)
    if target == "resources": return app.resources(subject)
    if target == "assessments": return app.assessments(subject)
    if target == "workspaces": return app.workspaces(subject)
    if target == "workspaces_page":
        try:
            parsed_page = int(page)
        except (TypeError, ValueError):
            return app._expired_route()
        return app.workspaces(subject, parsed_page)
    if target == "workspace_select": return app.select_workspace(subject, workspace) if workspace else app._expired_route()
    if target == "account": return app.account(subject)
    if target == "unlink_ask": return app.unlink_confirm(subject)
    if target == "unlink_do": return app.unlink(subject)
    if target == "course_open": return app.course_detail(subject, course) if course else app._expired_route()
    if target == "course_schedule": return app.course_schedule(subject, course) if course else app._expired_route()
    if target == "course_resources": return app.course_resources(subject, course) if course else app._expired_route()
    if target == "course_assessments": return app.course_assessments(subject, course) if course else app._expired_route()
    if target == "course_grades": return app.course_grades(subject, course) if course else app._expired_route()
    if target == "course_announcements": return app.course_announcements(subject, course) if course else app._expired_route()
    if target == "schedule_event_open": return app.schedule_event(subject, str(params.get("event_id") or ""))
    if target == "schedule_today": return app.day_schedule(subject, 0)
    if target == "schedule_tomorrow": return app.day_schedule(subject, 1)
    if target == "schedule_upcoming": return app.week_schedule(subject)
    if target == "announcements": return app.announcements(subject)
    if target == "forms": return app.forms(subject)
    if target == "form_open": return app.form_detail(subject, str(params.get("form") or ""))
    if target == "resource_open": return app.resource_detail(subject, resource) if resource else app.resources(subject)
    if target == "resource_deliver": return app.protected_resource(subject, resource) if resource else app._expired_route()
    if target == "protected":
        if job: return app.protected_media_ready(subject, job)
        if resource: return app.protected_resource(subject, resource)
        return app.resources(subject)
    if target == "payments": return app.payments(subject)
    # Page/filter intents without an opaque integration route recover safely to
    # their canonical list rather than trusting client cursor/page values.
    if target == "courses_page": return app.courses(subject)
    if target == "schedule_page": return app.week_schedule(subject)
    if target == "grades_page": return app.grades(subject)
    if target == "announcements_page": return app.announcements(subject)
    if target == "resources_page": return app.resources(subject)
    return app.home(subject)


__all__ = [
    "INTENT_REGISTRY",
    "core_to_runtime",
    "decode_v3_intent",
    "dispatch_v3_intent",
    "legacy_to_core",
    "resolve_screen_intents",
]
