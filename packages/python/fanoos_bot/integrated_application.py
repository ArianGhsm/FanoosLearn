from __future__ import annotations

from dataclasses import replace
from datetime import timedelta
from typing import Any

from .application import ApplicationConfig, BotApplication as BaseBotApplication
from .api import FanoosApiError
from .callbacks import CallbackCodec
from .formatting import format_human_number, format_time, is_uuid, truncate_text
from .localization import platform_label
from .models import ActionResult, Screen as RuntimeScreen
from .ui_v3.academic import (
    ANNOUNCEMENT_PAGE_SIZE,
    GRADE_PAGE_SIZE,
    SCHEDULE_PAGE_SIZE,
    announcement_detail_screen,
    announcement_list_screen,
    course_detail_screen,
    course_grade_detail_screen,
    course_list_screen,
    course_unavailable_screen,
    event_detail_screen,
    grade_list_screen,
    schedule_hub_screen,
    schedule_list_screen,
)
from .ui_v3.academic import actions as academic_actions
from .ui_v3.core import (
    Pagination,
    Screen as CoreScreen,
)
from .ui_v3.core.account import linked_account_screen, unlink_confirmation_screen, unlink_success_screen
from .ui_v3.core.actions import workspace_page_action
from .ui_v3.core.home import HomeSlot, SlotState, active_home_screen
from .ui_v3.core.onboarding import linked_no_workspace_screen, unlinked_account_screen
from .ui_v3.core.workspace import WorkspaceOption, no_workspace_screen, workspace_list_screen
from .ui_v3.learning import (
    assessment_hub_screen,
    commerce_hub_screen,
    form_detail_screen,
    forms_hub_screen,
    order_access_detail_screen,
    resource_detail_screen,
)
from .ui_v3.wiring import core_to_runtime, decode_v3_intent, dispatch_v3_intent

COURSE_PAGE_SIZE = 8
WORKSPACE_PAGE_SIZE = 5
_EMPTY_HISTORY_CURSOR = "~"
_OWNER_ALLOWED_KINDS = {
    "management",
    "deployment_confirmation",
    "deployment_status",
    "deployment_cancelled",
    "deployment_empty",
}


class BotApplication(BaseBotApplication):
    """Canonical business application with one V3 presentation/intent integration layer."""

    def _selected(self, subject: str):
        """Never turn a single membership into implicit selection authority."""
        projection = self._workspace_projection(subject)
        return projection, projection.get("selected_workspace_id")

    def _v3_result(self, screen: CoreScreen, **metadata: Any) -> ActionResult:
        return ActionResult(screen, metadata=dict(metadata))

    def _workspace_options(self, projection: dict, page: int = 0) -> tuple[WorkspaceOption, ...]:
        selected = str(projection.get("selected_workspace_id") or "")
        workspaces = [item for item in projection.get("workspaces") or [] if isinstance(item, dict)]
        start = max(0, page) * WORKSPACE_PAGE_SIZE
        options = []
        for workspace in workspaces[start : start + WORKSPACE_PAGE_SIZE]:
            workspace_id = str(workspace.get("id") or "")
            if not is_uuid(workspace_id):
                continue
            options.append(
                WorkspaceOption(
                    workspace_id=workspace_id,
                    label=self._workspace_label(workspace),
                    selected=workspace_id == selected,
                )
            )
        return tuple(options)

    def _no_workspace_screen(self) -> CoreScreen:
        return linked_no_workspace_screen(self.config.web_base_url)

    def _unlinked_screen(self) -> CoreScreen:
        return unlinked_account_screen(self.config.web_base_url)

    def _onboarding_error(self, exc: Exception):
        if isinstance(exc, FanoosApiError) and exc.code in {
            "messaging_link_required",
            "messaging_link_not_found",
        }:
            return self._v3_result(self._unlinked_screen())
        return self._error(exc)

    def _workspace_screen(self, projection: dict, page: int = 0) -> CoreScreen:
        if isinstance(page, bool) or not isinstance(page, int) or page < 0:
            raise ValueError("workspace page must be non-negative")
        workspaces = [
            item
            for item in projection.get("workspaces") or []
            if isinstance(item, dict) and is_uuid(item.get("id"))
        ]
        if not workspaces:
            return no_workspace_screen(self.config.web_base_url)
        total_pages = (len(workspaces) + WORKSPACE_PAGE_SIZE - 1) // WORKSPACE_PAGE_SIZE
        if page >= total_pages:
            raise IndexError("workspace page out of range")
        pagination = Pagination(
            page=page + 1,
            total_pages=total_pages,
            previous=(workspace_page_action(page - 1, next_page=False) if page > 0 else None),
            next=(workspace_page_action(page + 1, next_page=True) if page + 1 < total_pages else None),
            label="فهرست فضاهای آموزشی",
        )
        visible_projection = dict(projection)
        visible_projection["workspaces"] = workspaces
        return workspace_list_screen(
            self._workspace_options(visible_projection, page),
            pagination=pagination if total_pages > 1 else None,
        )

    def _home_schedule_slot(self, subject: str, workspace_id: str) -> HomeSlot:
        try:
            today, timezone_name, _ = self._today_projection(subject, workspace_id)
            items = [item for item in today.get("items") or [] if isinstance(item, dict)]
            if not items:
                return HomeSlot(
                    "برنامه امروز",
                    "برای امروز برنامه‌ای ثبت نشده است.",
                    state=SlotState.EMPTY,
                )
            first = items[0]
            title = truncate_text(
                first.get("course_title") or first.get("title") or "رویداد آموزشی",
                90,
            )
            when = format_time(first.get("starts_at"), timezone_name)
            location = truncate_text(first.get("location_text") or "", 70)
            detail = f"ساعت {when}" + (f" · {location}" if location else "")
            return HomeSlot("برنامه نزدیک", title, detail, SlotState.CONTENT)
        except Exception:
            return HomeSlot(
                "برنامه",
                "برنامه فعلاً قابل دریافت نیست.",
                "بعداً دوباره بررسی کنید.",
                SlotState.UNAVAILABLE,
            )

    def _home_announcement_slot(self, subject: str, workspace_id: str) -> HomeSlot:
        try:
            items = self.backend.announcements(
                self.platform, subject, workspace_id, 1, None
            ).get("items") or []
            item = next((value for value in items if isinstance(value, dict)), None)
            if item is None:
                return HomeSlot(
                    "اطلاعیه تازه",
                    "اطلاعیه تازه‌ای منتشر نشده است.",
                    state=SlotState.EMPTY,
                )
            return HomeSlot(
                "اطلاعیه تازه",
                truncate_text(item.get("title") or "اطلاعیه", 100),
                state=SlotState.CONTENT,
            )
        except Exception:
            return HomeSlot(
                "اطلاعیه‌ها",
                "اطلاعیه‌ها فعلاً قابل دریافت نیستند.",
                "بعداً دوباره بررسی کنید.",
                SlotState.UNAVAILABLE,
            )

    def home(self, subject: str, notice: str = ""):
        try:
            projection, selected = self._selected(subject)
            workspaces = [item for item in projection.get("workspaces") or [] if isinstance(item, dict)]
            if not workspaces:
                return self._v3_result(self._no_workspace_screen())
            if not selected:
                # Membership exists but selection does not: selection remains an
                # explicit user action and no first-workspace authority is invented.
                return self._v3_result(self._workspace_screen(projection))

            screen = active_home_screen(
                self._selected_workspace_label(projection, selected),
                next_schedule=self._home_schedule_slot(subject, selected),
                latest_announcement=self._home_announcement_slot(subject, selected),
            )
            if notice:
                screen = replace(screen, footer=f"✅ {truncate_text(notice, 180)}")
            return self._v3_result(screen)
        except Exception as exc:
            return self._onboarding_error(exc)

    def workspaces(self, subject: str, page: int = 0):
        try:
            if isinstance(page, bool) or not isinstance(page, int) or page < 0:
                return self._expired_route()
            projection = self._workspace_projection(subject)
            return self._v3_result(self._workspace_screen(projection, page))
        except Exception as exc:
            if isinstance(exc, IndexError):
                return self._expired_route()
            return self._onboarding_error(exc)

    def select_workspace(self, subject: str, workspace_id: str):
        if not is_uuid(workspace_id):
            return self._expired_route()
        try:
            # Canonical backend rechecks linked user + current membership.
            self.backend.select_workspace(self.platform, subject, workspace_id)
            projection = self._workspace_projection(subject)
            label = self._selected_workspace_label(projection, workspace_id)
            result = self.home(subject, f"فضای آموزشی «{label}» فعال شد.")
            return result
        except Exception as exc:
            return self._onboarding_error(exc)

    def account(self, subject: str):
        try:
            projection, selected = self._selected(subject)
            return self._v3_result(
                linked_account_screen(
                    platform_label=platform_label(self.platform),
                    website_url=self.config.web_base_url,
                    active_workspace_label=self._selected_workspace_label(projection, selected) if selected else None,
                    workspace_count_label=format_human_number(len(projection.get("workspaces") or [])),
                )
            )
        except Exception as exc:
            return self._onboarding_error(exc)

    def unlink_confirm(self, subject: str):
        return self._v3_result(unlink_confirmation_screen(platform_label=platform_label(self.platform)))

    def unlink(self, subject: str):
        try:
            self.backend.unlink(self.platform, subject)
            return self._v3_result(unlink_success_screen(self.config.web_base_url))
        except Exception as exc:
            return self._error(exc)

    def _courses(self, subject: str, workspace_id: str):
        """Use only the backend's authorized canonical course projection.

        Internal-v1 currently exposes this projection nested in schedule. Activity
        rows, grades and resources are not allowed to invent course truth.
        """
        projection, _, _ = self._today_projection(subject, workspace_id)
        normalized = []
        for row in projection.get("courses") or []:
            if not isinstance(row, dict) or not is_uuid(row.get("course_id")):
                continue
            title = str(row.get("course_title") or row.get("title") or "").strip()
            if not title:
                continue
            item = dict(row)
            # Keep the canonical projection keys and add compatibility aliases
            # for inherited course-scoped readers; neither is user authority.
            item.setdefault("course_title", title)
            item.setdefault("title", title)
            if row.get("course_code") and not row.get("code"):
                item["code"] = row["course_code"]
            normalized.append(item)
        return normalized

    def _course_page_ref(self, subject: str, page: int) -> str:
        return self.state.create_route(
            self.platform,
            subject,
            "v3_intent",
            {"name": "academic.courses.page", "params": {"page": str(page)}},
        )

    def _academic_route_ref(self, subject: str, name: str, params: dict[str, object] | None = None) -> str:
        return self.state.create_route(
            self.platform,
            subject,
            "v3_intent",
            {
                "name": name,
                "params": {str(key): str(value) for key, value in (params or {}).items()},
            },
        )

    def _route_history(self, raw: object) -> list[str]:
        if isinstance(raw, list):
            values = raw
        else:
            values = str(raw or "").split(",") if raw not in (None, "") else []
        result: list[str] = []
        for value in values:
            raw_value = str(value)
            if raw_value == _EMPTY_HISTORY_CURSOR:
                result.append("")
            elif raw_value == "" or self._clean_cursor(raw_value) is not None:
                result.append(raw_value)
        return result

    @staticmethod
    def _encode_history(values: list[str]) -> str:
        return ",".join(value or _EMPTY_HISTORY_CURSOR for value in values)

    def _event_route_refs(
        self,
        subject: str,
        items: list[dict],
        *,
        course_id: str | None = None,
    ) -> dict[str, str]:
        refs: dict[str, str] = {}
        for item in items:
            event_id = str(item.get("id") or item.get("event_id") or "")
            if not is_uuid(event_id):
                continue
            params: dict[str, object] = {"event_id": event_id}
            if course_id and is_uuid(course_id):
                params["course_id"] = course_id
            refs[event_id] = self._academic_route_ref(
                subject, "academic.schedule.event.open", params
            )
        return refs

    def courses(self, subject: str, page: int = 0):
        try:
            projection, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            if isinstance(page, bool) or not isinstance(page, int) or page < 0:
                return self._expired_route()
            courses = self._courses(subject, workspace_id)
            if not courses:
                return self._v3_result(
                    course_list_screen((), workspace_label=self._selected_workspace_label(projection, workspace_id))
                )
            start = page * COURSE_PAGE_SIZE
            if start >= len(courses):
                return self._expired_route()
            previous_ref = self._course_page_ref(subject, page - 1) if page > 0 else None
            next_ref = self._course_page_ref(subject, page + 1) if start + COURSE_PAGE_SIZE < len(courses) else None
            return self._v3_result(
                course_list_screen(
                    courses[start : start + COURSE_PAGE_SIZE],
                    page=page + 1,
                    previous_ref=previous_ref,
                    next_ref=next_ref,
                    workspace_label=self._selected_workspace_label(projection, workspace_id),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_detail(self, subject: str, course_id: str):
        if not is_uuid(course_id):
            return self._expired_route()
        try:
            projection, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = next(
                (item for item in self._courses(subject, workspace_id) if str(item.get("course_id") or "") == course_id),
                None,
            )
            if not course:
                return self._v3_result(course_unavailable_screen())
            return self._v3_result(
                course_detail_screen(
                    course,
                    supported_actions=("schedule", "resources", "grades", "announcements"),
                    workspace_label=self._selected_workspace_label(projection, workspace_id),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_schedule(self, subject: str, course_id: str, page: int = 0):
        if not is_uuid(course_id):
            return self._expired_route()
        try:
            projection, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = next(
                (item for item in self._courses(subject, workspace_id) if str(item.get("course_id") or "") == course_id),
                None,
            )
            if not course or isinstance(page, bool) or not isinstance(page, int) or page < 0:
                return self._expired_route() if page else self._v3_result(course_unavailable_screen())
            items, timezone_name = self._schedule_window(subject, workspace_id)
            filtered = [
                item for item in items
                if str(item.get("course_id") or "") == course_id
            ]
            start = page * SCHEDULE_PAGE_SIZE
            if start >= len(filtered) and page > 0:
                return self._expired_route()
            previous_ref = (
                self._academic_route_ref(
                    subject,
                    "academic.schedule.page",
                    {"page": page - 1, "course_id": course_id},
                )
                if page > 0
                else None
            )
            next_ref = (
                self._academic_route_ref(
                    subject,
                    "academic.schedule.page",
                    {"page": page + 1, "course_id": course_id},
                )
                if start + SCHEDULE_PAGE_SIZE < len(filtered)
                else None
            )
            return self._v3_result(
                schedule_list_screen(
                    filtered[start : start + SCHEDULE_PAGE_SIZE],
                    label="برنامه درس",
                    timezone_name=timezone_name,
                    page=page + 1,
                    previous_ref=previous_ref,
                    next_ref=next_ref,
                    course=course,
                    event_route_refs=self._event_route_refs(subject, filtered[start : start + SCHEDULE_PAGE_SIZE], course_id=course_id),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_grades(self, subject: str, course_id: str):
        if not is_uuid(course_id):
            return self._expired_route()
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = next(
                (item for item in self._courses(subject, workspace_id) if str(item.get("course_id") or "") == course_id),
                None,
            )
            if course is None:
                return self._v3_result(course_unavailable_screen())
            projection = self.backend.grades(
                self.platform, subject, workspace_id, 100, None
            )
            items = [item for item in projection.get("items") or [] if isinstance(item, dict)]
            return self._v3_result(course_grade_detail_screen(course, items))
        except Exception as exc:
            return self._error(exc)

    def schedule_menu(self, subject: str):
        try:
            _, _, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            return self._v3_result(schedule_hub_screen())
        except Exception as exc:
            return self._error(exc)

    def day_schedule(self, subject: str, offset: int):
        if offset not in (0, 1):
            return self._expired_route()
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            projection, timezone_name, local_today = self._today_projection(subject, workspace_id)
            if offset:
                projection, timezone_name = self._schedule_for_date(
                    subject, workspace_id, local_today + timedelta(days=offset)
                )
            label = "امروز" if offset == 0 else "فردا"
            items = [item for item in projection.get("items") or [] if isinstance(item, dict)]
            return self._v3_result(
                schedule_list_screen(
                    items,
                    label=label,
                    timezone_name=timezone_name,
                    event_route_refs=self._event_route_refs(subject, items),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def week_schedule(self, subject: str, page: int = 0):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            if isinstance(page, bool) or not isinstance(page, int) or page < 0:
                return self._expired_route()
            items, timezone_name = self._schedule_window(subject, workspace_id)
            start = page * SCHEDULE_PAGE_SIZE
            if start >= len(items) and page > 0:
                return self._expired_route()
            previous_ref = (
                self._academic_route_ref(subject, "academic.schedule.page", {"page": page - 1})
                if page > 0
                else None
            )
            next_ref = (
                self._academic_route_ref(subject, "academic.schedule.page", {"page": page + 1})
                if start + SCHEDULE_PAGE_SIZE < len(items)
                else None
            )
            return self._v3_result(
                schedule_list_screen(
                    items[start : start + SCHEDULE_PAGE_SIZE],
                    label="۷ روز آینده",
                    timezone_name=timezone_name,
                    page=page + 1,
                    previous_ref=previous_ref,
                    next_ref=next_ref,
                    event_route_refs=self._event_route_refs(subject, items[start : start + SCHEDULE_PAGE_SIZE]),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def schedule_event(self, subject: str, event_id: str):
        if not is_uuid(event_id):
            return self._expired_route()
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            items, timezone_name = self._schedule_window(subject, workspace_id)
            event = next(
                (item for item in items if str(item.get("id") or item.get("event_id") or "") == event_id),
                None,
            )
            if event is None:
                return self._expired_route()
            return self._v3_result(event_detail_screen(event, timezone_name=timezone_name))
        except Exception as exc:
            return self._error(exc)

    def grades(
        self,
        subject: str,
        cursor: str | None = None,
        history: list[str] | str | None = None,
    ):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            cursor = self._clean_cursor(cursor)
            history_values = self._route_history(history)
            projection = self.backend.grades(
                self.platform, subject, workspace_id, GRADE_PAGE_SIZE, cursor
            )
            items = [item for item in projection.get("items") or [] if isinstance(item, dict)]
            next_cursor = self._clean_cursor(projection.get("next_cursor"))
            previous_ref = None
            if history_values:
                previous_ref = self._academic_route_ref(
                    subject,
                    "academic.grades.page",
                    {
                        "cursor": history_values[-1],
                        "history": self._encode_history(history_values[:-1]),
                    },
                )
            next_ref = None
            if next_cursor:
                next_ref = self._academic_route_ref(
                    subject,
                    "academic.grades.page",
                    {
                        "cursor": next_cursor,
                        "history": self._encode_history(history_values + [cursor or ""]),
                    },
                )
            return self._v3_result(
                grade_list_screen(
                    items,
                    page=len(history_values) + 1,
                    previous_ref=previous_ref,
                    next_ref=next_ref,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def _announcements_projection(
        self,
        subject: str,
        workspace_id: str,
        cursor: str | None,
        *,
        course_id: str | None = None,
    ) -> dict:
        method = self.backend.announcements
        if course_id:
            try:
                projection = method(
                    self.platform,
                    subject,
                    workspace_id,
                    ANNOUNCEMENT_PAGE_SIZE,
                    cursor,
                    course_id=course_id,
                )
            except TypeError:
                projection = method(
                    self.platform, subject, workspace_id, ANNOUNCEMENT_PAGE_SIZE, cursor
                )
                rows = [item for item in projection.get("items") or [] if isinstance(item, dict)]
                projection = dict(projection)
                projection["items"] = [
                    item for item in rows
                    if str(item.get("course_id") or item.get("scope_course_id") or "") == course_id
                ]
            return projection if isinstance(projection, dict) else {"items": []}
        projection = method(
            self.platform, subject, workspace_id, ANNOUNCEMENT_PAGE_SIZE, cursor
        )
        return projection if isinstance(projection, dict) else {"items": []}

    def announcements(
        self,
        subject: str,
        cursor: str | None = None,
        history: list[str] | str | None = None,
        course_id: str | None = None,
    ):
        if course_id is not None and not is_uuid(course_id):
            return self._expired_route()
        try:
            projection, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            cursor = self._clean_cursor(cursor)
            history_values = self._route_history(history)
            course = None
            if course_id:
                course = next(
                    (
                        item
                        for item in self._courses(subject, workspace_id)
                        if str(item.get("course_id") or "") == course_id
                    ),
                    None,
                )
                if course is None:
                    return self._v3_result(course_unavailable_screen())
            page = self._announcements_projection(
                subject, workspace_id, cursor, course_id=course_id
            )
            items = [item for item in page.get("items") or [] if isinstance(item, dict)]
            next_cursor = self._clean_cursor(page.get("next_cursor"))
            previous_ref = None
            if history_values:
                previous_ref = self._academic_route_ref(
                    subject,
                    "academic.announcements.page",
                    {
                        "cursor": history_values[-1],
                        "history": self._encode_history(history_values[:-1]),
                        **({"course_id": course_id} if course_id else {}),
                    },
                )
            next_ref = None
            if next_cursor:
                next_ref = self._academic_route_ref(
                    subject,
                    "academic.announcements.page",
                    {
                        "cursor": next_cursor,
                        "history": self._encode_history(history_values + [cursor or ""]),
                        **({"course_id": course_id} if course_id else {}),
                    },
                )
            detail_refs = {
                str(item.get("id") or item.get("announcement_id")): self._academic_route_ref(
                    subject,
                    "academic.announcement.open",
                    {
                        "announcement_id": str(item.get("id") or item.get("announcement_id")),
                        **({"course_id": course_id} if course_id else {}),
                    },
                )
                for item in items
                if is_uuid(str(item.get("id") or item.get("announcement_id") or ""))
            }
            return self._v3_result(
                announcement_list_screen(
                    items,
                    page=len(history_values) + 1,
                    previous_ref=previous_ref,
                    next_ref=next_ref,
                    detail_route_refs=detail_refs,
                    course=course,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_announcements(self, subject: str, course_id: str):
        return self.announcements(subject, course_id=course_id)

    def _find_announcement(
        self,
        subject: str,
        workspace_id: str,
        announcement_id: str,
        *,
        course_id: str | None = None,
    ) -> dict | None:
        cursor = None
        for _ in range(3):
            projection = self._announcements_projection(
                subject, workspace_id, cursor, course_id=course_id
            )
            for item in projection.get("items") or []:
                if isinstance(item, dict) and str(item.get("id") or item.get("announcement_id") or "") == announcement_id:
                    return item
            cursor = self._clean_cursor(projection.get("next_cursor"))
            if not cursor:
                break
        return None

    def announcement_detail(self, subject: str, announcement_id: str, course_id: str | None = None):
        if not is_uuid(announcement_id) or (course_id is not None and not is_uuid(course_id)):
            return self._expired_route()
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = None
            if course_id:
                course = next(
                    (
                        item
                        for item in self._courses(subject, workspace_id)
                        if str(item.get("course_id") or "") == course_id
                    ),
                    None,
                )
                if course is None:
                    return self._v3_result(course_unavailable_screen())
            item = self._find_announcement(
                subject,
                workspace_id,
                announcement_id,
                course_id=course_id,
            )
            if item is None:
                return self._expired_route()
            safe_link = str(item.get("url") or item.get("web_url") or "")
            if not safe_link.startswith(("https://", "http://")):
                safe_link = None
            return self._v3_result(
                announcement_detail_screen(
                    item,
                    safe_link_ref=safe_link,
                    course_title=str((course or {}).get("course_title") or (course or {}).get("title") or ""),
                    back_action=academic_actions.COURSE_OPEN if course else academic_actions.ANNOUNCEMENTS,
                    back_payload={"course_id": course_id} if course_id else None,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def forms(self, subject: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            method = getattr(self.backend, "forms", None)
            if not callable(method):
                return self._v3_result(forms_hub_screen(None, web_url=self.config.web_base_url or None))
            try:
                projection = method(self.platform, subject, workspace_id, 100, None)
            except TypeError:
                # A future website-backed adapter may expose the existing
                # non-paginated forms(workspace) read while the bot-safe
                # projection is being standardized.
                projection = method(self.platform, subject, workspace_id)
            items = projection.get("items") if isinstance(projection, dict) else projection
            if not isinstance(items, (list, tuple)):
                items = []
            return self._v3_result(
                forms_hub_screen(items, web_url=self.config.web_base_url or None)
            )
        except Exception as exc:
            return self._error(exc)

    def form_detail(self, subject: str, form_id: str):
        if not is_uuid(form_id):
            return self._expired_route()
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            method = getattr(self.backend, "forms", None)
            if not callable(method):
                return self._v3_result(forms_hub_screen(None, web_url=self.config.web_base_url or None))
            try:
                projection = method(self.platform, subject, workspace_id, 100, None)
            except TypeError:
                projection = method(self.platform, subject, workspace_id)
            items = projection.get("items") if isinstance(projection, dict) else projection
            item = next(
                (
                    value
                    for value in (items if isinstance(items, (list, tuple)) else [])
                    if isinstance(value, dict)
                    and str(value.get("form_id") or value.get("id") or "") == form_id
                ),
                None,
            )
            if item is None:
                return self._expired_route()
            return self._v3_result(
                form_detail_screen(
                    item,
                    web_url=self.config.web_base_url or None,
                    timezone_name=str(item.get("timezone") or "") or None,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def resource_detail(self, subject: str, resource_id: str):
        if not is_uuid(resource_id):
            return self._expired_route()
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            item = self._find_resource(subject, workspace_id, resource_id)
            if not item:
                return super().resource_detail(subject, resource_id)
            return self._v3_result(
                resource_detail_screen(item, web_url=self.config.web_base_url or None)
            )
        except Exception as exc:
            return self._error(exc)

    def assessments(self, subject: str):
        try:
            _, _, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            return self._v3_result(
                assessment_hub_screen(None, web_url=self.config.web_base_url or None)
            )
        except Exception as exc:
            return self._error(exc)

    def payments(self, subject: str):
        try:
            _, selected = self._selected(subject)
            if not selected:
                return super().payments(subject)
            return self._v3_result(
                commerce_hub_screen(
                    order_summary=None,
                    access_summary=None,
                    catalog=None,
                    web_url=self.config.web_base_url or None,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def order_status(self, subject: str, order_id: str):
        try:
            _, workspace_id = self._selected(subject)
            if not workspace_id:
                return super().order_status(subject, order_id)
            order = self.backend.order_status(self.platform, subject, workspace_id, order_id)
            entitlement = order.get("entitlement") if isinstance(order.get("entitlement"), dict) else {}
            return self._v3_result(
                order_access_detail_screen(
                    order,
                    payment_status=order.get("payment_status"),
                    entitlement_status=entitlement.get("status"),
                    payment_url=order.get("payment_url"),
                    web_url=self.config.web_base_url or None,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def callback(self, subject: str, private: bool, value: str):
        decoded = decode_v3_intent(self, subject, value)
        if decoded is not None:
            name, params = decoded
            if name == "academic.courses.page":
                try:
                    page = int(params.get("page", ""))
                except (TypeError, ValueError):
                    return self._expired_route()
                return self.courses(subject, page)
            if name == "academic.schedule.page":
                try:
                    page = int(params.get("page", ""))
                except (TypeError, ValueError):
                    return self._expired_route()
                course_id = str(params.get("course_id") or "")
                return (
                    self.course_schedule(subject, course_id, page)
                    if course_id
                    else self.week_schedule(subject, page)
                )
            if name == "academic.grades.page":
                return self.grades(
                    subject,
                    self._clean_cursor(params.get("cursor")),
                    params.get("history", ""),
                )
            if name == "academic.announcements.page":
                return self.announcements(
                    subject,
                    self._clean_cursor(params.get("cursor")),
                    params.get("history", ""),
                    params.get("course_id") or None,
                )
            if name == "academic.announcement.open":
                return self.announcement_detail(
                    subject,
                    params.get("announcement_id", ""),
                    params.get("course_id") or None,
                )
            return dispatch_v3_intent(self, subject, private, name, params)

        try:
            action, ref = CallbackCodec.decode(value)
        except Exception:
            return super().callback(subject, private, value)
        if action == "coursep" and ref:
            payload = self._route_payload(subject, ref, "courses_page")
            page = (payload or {}).get("page")
            if isinstance(page, int) and not isinstance(page, bool) and page >= 0:
                return self.courses(subject, page)
            return self._expired_route()
        if action == "help":
            return self.help()
        return super().callback(subject, private, value)

    def prepare_result(self, subject: str, private: bool, result: ActionResult) -> ActionResult:
        """Attach canonical V3 semantics when present without replaying business logic.

        Existing legacy application screens retain their already-valid compact
        callback payloads and are adapted directly by bot-04. This compatibility
        path prevents the integration intent registry from reinterpreting legacy
        CallbackCodec values as new V3 semantic intents.
        """
        source = result.screen
        if isinstance(source, CoreScreen):
            runtime_screen = core_to_runtime(self, subject, source)
            screen_id = runtime_screen.v3.identifier
        elif isinstance(source, RuntimeScreen):
            runtime_screen = source
            screen_id = source.presentation.semantic_kind if source.presentation else "legacy"
        else:
            return result

        metadata = dict(result.metadata or {})
        metadata["v3_screen_id"] = screen_id
        legacy_kind = source.presentation.semantic_kind if isinstance(source, RuntimeScreen) and source.presentation else ""
        if (
            self.platform == "telegram"
            and private
            and legacy_kind in _OWNER_ALLOWED_KINDS
        ):
            # These source screens are reachable only after the canonical owner
            # backend checks in BaseBotApplication. This metadata does not grant
            # permission; it passes that already-verified fact to presentation.
            metadata["canonical_permissions"] = ("deployment.manage",)
        return replace(result, screen=runtime_screen, metadata=metadata)


__all__ = ["ApplicationConfig", "BotApplication"]
