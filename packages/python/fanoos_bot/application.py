from __future__ import annotations

import re
import secrets
from dataclasses import dataclass
from datetime import date, datetime, timedelta, timezone
from zoneinfo import ZoneInfo

from .api import FanoosApiError
from .callbacks import CallbackCodec
from .formatting import (
    format_datetime,
    format_human_number,
    format_money,
    format_score,
    format_time,
    humanize_slug,
    is_uuid,
    short_sha,
    truncate_text,
)
from .localization import (
    deployment_state_label,
    entitlement_label,
    error_message,
    health_status_label,
    order_status_label,
    platform_label,
    resource_type_label,
)
from .media_state import ProtectedMediaLocalState
from .models import ActionResult, Button, DeliveryReceiptContext, DocumentPayload, SemanticSection
from .presentation import error_screen, semantic_screen, success_screen, warning_screen
from .product_ui import course_catalog, filter_course, find_course

UUID_RE = re.compile(r"^[0-9a-f-]{36}$", re.I)
START_RE = re.compile(r"^[A-Za-z0-9_-]{1,64}$")
SAFE_IDEM = re.compile(r"^[A-Za-z0-9._:-]{1,160}$")
CURSOR_RE = re.compile(r"^[A-Za-z0-9_-]{1,32}$")


@dataclass(frozen=True)
class ApplicationConfig:
    web_base_url: str = ""
    deployment_target_key: str = ""
    protected_renderer_version: str = "fanoos-raster-v1"


class BotApplication:
    def __init__(self, backend, state, platform: str, config: ApplicationConfig = ApplicationConfig()):
        self.backend = backend
        self.state = state
        self.platform = platform
        self.config = config
        self.media_state = ProtectedMediaLocalState(state)

    @staticmethod
    def _cb(action: str, ref: str | None = None) -> str:
        return CallbackCodec.encode(action, ref)

    def _home_row(self) -> tuple[Button, ...]:
        return (Button("🏠 خانه", self._cb("home")),)

    def _nav_rows(
        self,
        *,
        back_action: str | None = None,
        back_ref: str | None = None,
        back_label: str = "‹ بازگشت",
    ) -> tuple[tuple[Button, ...], ...]:
        if back_action and back_action != "home":
            return ((Button(back_label, self._cb(back_action, back_ref)), Button("🏠 خانه", self._cb("home"))),)
        return (self._home_row(),)

    def _web_row(self, label: str = "🌐 باز کردن فانوس") -> tuple[tuple[Button, ...], ...]:
        if not self.config.web_base_url:
            return ()
        return ((Button(label, url=self.config.web_base_url),),)

    def _safe_error_rows(self) -> tuple[tuple[Button, ...], ...]:
        return (self._home_row(),)

    def _error(self, exc: Exception):
        if isinstance(exc, FanoosApiError):
            return ActionResult(
                error_screen(
                    error_message(exc.code, exc.status),
                    rows=self._safe_error_rows(),
                )
            )
        return ActionResult(
            error_screen(
                "سرویس موقتاً پاسخ نمی‌دهد. دوباره امتحان کنید.",
                rows=self._safe_error_rows(),
            )
        )

    @staticmethod
    def _workspace_label(workspace: dict) -> str:
        for key in ("name", "title", "label"):
            value = " ".join(str(workspace.get(key) or "").split())
            if value:
                return truncate_text(value, 70)
        slug = humanize_slug(workspace.get("slug"))
        return truncate_text(slug, 70) if slug else "فضای آموزشی"

    def _selected_workspace_label(self, projection: dict, selected: str | None) -> str:
        if not selected:
            return "انتخاب نشده"
        for workspace in projection.get("workspaces") or []:
            if str(workspace.get("id") or "") == selected:
                return self._workspace_label(workspace)
        return "فضای انتخاب‌شده"

    def _workspace_projection(self, subject: str):
        return self.backend.workspaces(self.platform, subject)

    def _selected(self, subject: str):
        projection = self._workspace_projection(subject)
        workspaces = projection.get("workspaces") or []
        selected = projection.get("selected_workspace_id")
        if not selected and len(workspaces) == 1:
            self.backend.select_workspace(self.platform, subject, workspaces[0]["id"])
            selected = workspaces[0]["id"]
        return projection, selected

    def _workspace_or_result(self, subject: str):
        projection, selected = self._selected(subject)
        if not selected:
            rows = ((Button("🏫 انتخاب فضای آموزشی", self._cb("workspaces")),),) + self._nav_rows()
            return (
                projection,
                None,
                ActionResult(
                    warning_screen(
                        "ابتدا یک فضای آموزشی فعال انتخاب کنید.",
                        title="🏫 فضای آموزشی",
                        kind="workspace_required",
                        rows=rows,
                    )
                ),
            )
        return projection, selected, None

    @staticmethod
    def _clean_cursor(value: object) -> str | None:
        raw = str(value or "")
        return raw if CURSOR_RE.fullmatch(raw) else None

    def _route_callback(self, subject: str, kind: str, action: str, payload: dict) -> str:
        ref = self.state.create_route(self.platform, subject, kind, payload)
        return self._cb(action, ref)

    def _route_payload(self, subject: str, ref: str, kind: str) -> dict | None:
        row = self.state.route(ref, self.platform, subject, kind=kind)
        if not row:
            return None
        payload = row.get("payload")
        return payload if isinstance(payload, dict) else None

    def _expired_route(self) -> ActionResult:
        return ActionResult(
            warning_screen(
                "این صفحه منقضی شده یا برای این حساب نیست. از خانه دوباره وارد بخش موردنظر شوید.",
                title="⚠️ صفحه در دسترس نیست",
                kind="route_expired",
                rows=self._nav_rows(),
            )
        )

    def start(self, subject: str, payload: str | None = None):
        if payload:
            if self.platform != "telegram" or not START_RE.fullmatch(payload):
                return ActionResult(
                    error_screen(
                        "درخواست اتصال نامعتبر است. از فانوس یک کد اتصال تازه بگیرید.",
                        kind="link_error",
                    )
                )
            return self.link(subject, payload)
        return self.home(subject)

    def link(self, subject: str, token: str):
        if not token or len(token) > 128 or not re.fullmatch(r"[A-Za-z0-9_-]+", token):
            return ActionResult(
                error_screen(
                    "کد اتصال معتبر نیست. از فانوس یک کد اتصال تازه بگیرید.",
                    kind="link_error",
                )
            )
        try:
            self.backend.consume_link(self.platform, subject, token)
            return ActionResult(
                success_screen(
                    f"این {platform_label(self.platform)} به حساب فانوس شما متصل شد.",
                    title="✅ اتصال انجام شد",
                    kind="link_success",
                    rows=((Button("🏠 ورود به فانوس", self._cb("home")),),),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def unlink_confirm(self, subject: str):
        rows = (
            (Button("❌ قطع اتصال", self._cb("unlink")), Button("لغو", self._cb("account"))),
            (Button("🏠 خانه", self._cb("home")),),
        )
        return ActionResult(
            warning_screen(
                f"فقط اتصال همین {platform_label(self.platform)} قطع می‌شود؛ حساب اصلی فانوس و اطلاعات آموزشی شما حذف نمی‌شود.",
                title="👤 قطع اتصال پیام‌رسان",
                kind="unlink_confirmation",
                breadcrumb="بیشتر › حساب",
                rows=rows,
            )
        )

    def unlink(self, subject: str):
        try:
            self.backend.unlink(self.platform, subject)
            return ActionResult(
                success_screen(
                    f"اتصال {platform_label(self.platform)} قطع شد. حساب اصلی فانوس شما حذف نشده است.",
                    title="✅ اتصال قطع شد",
                    kind="unlink_success",
                )
            )
        except Exception as exc:
            return self._error(exc)

    def _today_projection(self, subject: str, workspace_id: str):
        now_utc = datetime.now(timezone.utc)
        guessed = now_utc.date()
        projection = self.backend.schedule(
            self.platform, subject, workspace_id, guessed.isoformat(), guessed.isoformat(), 20, None
        )
        timezone_name = str(projection.get("timezone") or "UTC")
        try:
            local_date = now_utc.astimezone(ZoneInfo(timezone_name)).date()
        except Exception:
            local_date = guessed
        if local_date != guessed:
            projection = self.backend.schedule(
                self.platform, subject, workspace_id, local_date.isoformat(), local_date.isoformat(), 20, None
            )
            timezone_name = str(projection.get("timezone") or timezone_name)
        return projection, timezone_name, local_date

    def home(self, subject: str, notice: str = ""):
        try:
            projection, selected = self._selected(subject)
            workspaces = projection.get("workspaces") or []
            if not workspaces:
                rows = self._web_row() or ()
                return ActionResult(
                    semantic_screen(
                        "🏠 فانوس",
                        "home_empty",
                        severity="warning",
                        intro="حساب شما متصل است، اما فضای آموزشی فعالی برای این حساب وجود ندارد.",
                        rows=rows,
                    )
                )

            sections: list[SemanticSection] = []
            if selected:
                try:
                    today, timezone_name, _ = self._today_projection(subject, selected)
                    items = today.get("items") or []
                    if items:
                        first = items[0]
                        title = truncate_text(
                            first.get("course_title") or first.get("title") or "رویداد آموزشی", 90
                        )
                        when = format_time(first.get("starts_at"), timezone_name)
                        location = truncate_text(first.get("location_text") or "", 80)
                        detail = [f"ساعت {when}"]
                        if location:
                            detail.append(location)
                        sections.append(SemanticSection("📅 برنامه نزدیک", title, tuple(detail)))
                    else:
                        sections.append(SemanticSection("📅 امروز", "برای امروز برنامه‌ای ثبت نشده است."))
                except Exception:
                    pass
                try:
                    latest = self.backend.announcements(
                        self.platform, subject, selected, 1, None
                    ).get("items") or []
                    if latest:
                        item = latest[0]
                        sections.append(
                            SemanticSection(
                                "📢 اطلاعیه تازه",
                                truncate_text(item.get("title") or "اطلاعیه", 100),
                            )
                        )
                except Exception:
                    pass

            rows = (
                (Button("📚 درس‌ها", self._cb("courses")), Button("📅 برنامه", self._cb("schedule"))),
                (Button("🔔 اعلان‌ها", self._cb("notifs")), Button("➕ بیشتر", self._cb("more"))),
                (Button("📅 امروز", self._cb("today")),),
            )
            if self.config.web_base_url:
                rows += self._web_row()
            return ActionResult(
                semantic_screen(
                    "🏠 فانوس",
                    "home",
                    intro="نمای سریع فضای آموزشی شما",
                    facts=(("فضای فعال", self._selected_workspace_label(projection, selected)),),
                    sections=sections[:2],
                    footer=f"✅ {notice}" if notice else "",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def more(self, subject: str, private: bool):
        try:
            _, selected, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            rows: tuple[tuple[Button, ...], ...] = (
                (Button("🎓 نمرات", self._cb("grades")), Button("📚 منابع", self._cb("resources"))),
                (Button("📝 آزمون‌ها", self._cb("assess")), Button("💳 خرید و دسترسی", self._cb("payments"))),
                (Button("🏫 فضای آموزشی", self._cb("workspaces")), Button("👤 حساب", self._cb("account"))),
            )
            if (
                self.platform == "telegram"
                and private
                and self.config.deployment_target_key
            ):
                try:
                    overview = self.backend.deployment_overview(
                        subject, self.config.deployment_target_key
                    )
                    if overview.get("can_manage_deployments") is True:
                        rows += ((Button("⚙️ مدیریت", self._cb("manage")),),)
                except Exception:
                    pass
            rows += self._nav_rows()
            return ActionResult(
                semantic_screen(
                    "➕ بیشتر",
                    "more",
                    intro="سایر بخش‌های فانوس",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def _schedule_window(self, subject: str, workspace_id: str, *, max_pages: int = 3) -> tuple[list[dict], str]:
        _, timezone_name, local_today = self._today_projection(subject, workspace_id)
        end = local_today + timedelta(days=30)
        items: list[dict] = []
        cursor = None
        for _ in range(max_pages):
            projection = self.backend.schedule(
                self.platform,
                subject,
                workspace_id,
                local_today.isoformat(),
                end.isoformat(),
                100,
                cursor,
            )
            timezone_name = str(projection.get("timezone") or timezone_name)
            items.extend(item for item in projection.get("items") or [] if isinstance(item, dict))
            cursor = self._clean_cursor(projection.get("next_cursor"))
            if not cursor:
                break
        return items, timezone_name

    def _grade_items(self, subject: str, workspace_id: str, *, max_pages: int = 3) -> list[dict]:
        items: list[dict] = []
        cursor = None
        for _ in range(max_pages):
            projection = self.backend.grades(
                self.platform, subject, workspace_id, 100, cursor
            )
            items.extend(item for item in projection.get("items") or [] if isinstance(item, dict))
            cursor = self._clean_cursor(projection.get("next_cursor"))
            if not cursor:
                break
        return items

    def _resource_items(self, subject: str, workspace_id: str, *, max_pages: int = 3) -> list[dict]:
        items: list[dict] = []
        cursor = None
        for _ in range(max_pages):
            projection = self.backend.resources(
                self.platform, subject, workspace_id, 100, cursor
            )
            items.extend(item for item in projection.get("items") or [] if isinstance(item, dict))
            cursor = self._clean_cursor(projection.get("next_cursor"))
            if not cursor:
                break
        return items

    def _courses(self, subject: str, workspace_id: str):
        schedule_items: list[dict] = []
        grade_items: list[dict] = []
        resource_items: list[dict] = []
        successful = 0
        try:
            schedule_items, _ = self._schedule_window(subject, workspace_id)
            successful += 1
        except FanoosApiError as exc:
            if exc.status not in {403, 404}:
                raise
        try:
            grade_items = self._grade_items(subject, workspace_id)
            successful += 1
        except FanoosApiError as exc:
            if exc.status not in {403, 404}:
                raise
        try:
            resource_items = self._resource_items(subject, workspace_id)
            successful += 1
        except FanoosApiError as exc:
            if exc.status not in {403, 404}:
                raise
        if not successful:
            raise FanoosApiError("permission_denied", "course projections unavailable", 403)
        return course_catalog(schedule_items, grade_items, resource_items)

    def courses(self, subject: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            courses = self._courses(subject, workspace_id)
            if not courses:
                rows = self._web_row() + self._nav_rows()
                return ActionResult(
                    semantic_screen(
                        "📚 درس‌ها",
                        "course_list_empty",
                        intro="در برنامه، منابع یا نمرات فعلی درسی برای نمایش پیدا نشد.",
                        footer="فهرست کامل درس‌های بدون فعالیت به projection مستقل backend نیاز دارد.",
                        rows=rows,
                    )
                )
            rows = tuple(
                (Button(truncate_text(course["title"], 36), self._cb("course", course["course_id"])),)
                for course in courses[:30]
            )
            rows += self._nav_rows()
            return ActionResult(
                semantic_screen(
                    "📚 درس‌ها",
                    "course_list",
                    intro="یک درس را برای دیدن برنامه، منابع و نمرات همان درس انتخاب کنید.",
                    list_items=tuple(
                        f"{course['title']}" + (f" · {course['code']}" if course.get("code") else "")
                        for course in courses[:30]
                    ),
                    footer=(
                        "این فهرست از داده‌های مجاز برنامه، منابع و نمرات ساخته می‌شود."
                        if len(courses) <= 30
                        else "۳۰ درس اول نمایش داده شده است."
                    ),
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def _canonical_course(self, subject: str, workspace_id: str, course_id: str) -> dict | None:
        if not is_uuid(course_id):
            return None
        return find_course(self._courses(subject, workspace_id), course_id)

    def course_detail(self, subject: str, course_id: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = self._canonical_course(subject, workspace_id, course_id)
            if not course:
                return ActionResult(
                    warning_screen(
                        "این درس در داده‌های مجاز فعلی پیدا نشد. فهرست درس‌ها را دوباره باز کنید.",
                        title="📚 درس در دسترس نیست",
                        kind="course_unavailable",
                        rows=((Button("‹ درس‌ها", self._cb("courses")),),) + self._nav_rows(),
                    )
                )
            title = course["title"]
            rows = (
                (Button("📅 برنامه", self._cb("csched", course_id)), Button("📚 منابع", self._cb("cres", course_id))),
                (Button("📝 آزمون‌ها", self._cb("cassess", course_id)), Button("🎓 نمرات", self._cb("cgrades", course_id))),
                (Button("📢 اطلاعیه‌ها", self._cb("cann", course_id)),),
                (Button("‹ بازگشت به درس‌ها", self._cb("courses")), Button("🏠 خانه", self._cb("home"))),
            )
            facts = (("کد درس", course["code"]),) if course.get("code") else ()
            return ActionResult(
                semantic_screen(
                    f"📚 {title}",
                    "course_detail",
                    breadcrumb=f"درس‌ها › {title}",
                    facts=facts,
                    intro="بخش موردنظر این درس را انتخاب کنید.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_schedule(self, subject: str, course_id: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = self._canonical_course(subject, workspace_id, course_id)
            if not course:
                return self._expired_route()
            items, timezone_name = self._schedule_window(subject, workspace_id)
            filtered = filter_course(items, course_id)
            sections = self._schedule_sections(filtered[:30], timezone_name)
            intro = "" if sections else "در ۳۱ روز آینده برنامه‌ای برای این درس ثبت نشده است."
            return ActionResult(
                semantic_screen(
                    "📅 برنامه درس",
                    "course_schedule",
                    breadcrumb=f"درس‌ها › {course['title']} › برنامه",
                    intro=intro,
                    sections=sections,
                    footer="بازه نمایش: ۳۱ روز آینده",
                    rows=self._nav_rows(back_action="course", back_ref=course_id, back_label="‹ بازگشت به درس"),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_resources(self, subject: str, course_id: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = self._canonical_course(subject, workspace_id, course_id)
            if not course:
                return self._expired_route()
            filtered = filter_course(self._resource_items(subject, workspace_id), course_id)
            rows: tuple[tuple[Button, ...], ...] = ()
            display = []
            for item in filtered[:20]:
                title = truncate_text(item.get("title") or "منبع", 90)
                type_label = resource_type_label(
                    item.get("type_key") or item.get("resource_type") or item.get("type") or item.get("kind")
                )
                display.append(f"{title} · {type_label}")
                rid = str(item.get("resource_id") or "")
                if is_uuid(rid):
                    rows += ((Button(truncate_text(title, 38), self._cb("resdetail", rid)),),)
            if not display:
                intro = "منبع قابل‌دسترسی‌ای برای این درس پیدا نشد."
            else:
                intro = ""
            rows += self._nav_rows(back_action="course", back_ref=course_id, back_label="‹ بازگشت به درس")
            return ActionResult(
                semantic_screen(
                    "📚 منابع درس",
                    "course_resources",
                    breadcrumb=f"درس‌ها › {course['title']} › منابع",
                    intro=intro,
                    list_items=display,
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_grades(self, subject: str, course_id: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = self._canonical_course(subject, workspace_id, course_id)
            if not course:
                return self._expired_route()
            filtered = filter_course(self._grade_items(subject, workspace_id), course_id)
            display = self._grade_lines(filtered[:30], include_course=False)
            return ActionResult(
                semantic_screen(
                    "🎓 نمرات درس",
                    "course_grades",
                    breadcrumb=f"درس‌ها › {course['title']} › نمرات",
                    intro="" if display else "نمره منتشرشده‌ای برای این درس پیدا نشد.",
                    list_items=display,
                    footer="میانگین یا معدل فقط در صورت وجود projection کامل و policy مشخص نمایش داده می‌شود.",
                    rows=self._nav_rows(back_action="course", back_ref=course_id, back_label="‹ بازگشت به درس"),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def course_announcements(self, subject: str, course_id: str):
        return self._course_gap(
            subject,
            course_id,
            "📢 اطلاعیه‌های درس",
            "course_announcements_gap",
            "اطلاعیه‌های فعلی backend شناسه درس ندارند؛ بنابراین فیلتر کردن آن‌ها بر اساس درس قابل‌اعتماد نیست.",
        )

    def course_assessments(self, subject: str, course_id: str):
        return self._course_gap(
            subject,
            course_id,
            "📝 آزمون‌های درس",
            "course_assessments_gap",
            "projection امن آزمون برای ربات هنوز در قرارداد داخلی فانوس وجود ندارد.",
        )

    def _course_gap(self, subject: str, course_id: str, title: str, kind: str, message: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            course = self._canonical_course(subject, workspace_id, course_id)
            if not course:
                return self._expired_route()
            rows = self._web_row() + self._nav_rows(
                back_action="course", back_ref=course_id, back_label="‹ بازگشت به درس"
            )
            return ActionResult(
                semantic_screen(
                    title,
                    kind,
                    severity="warning",
                    breadcrumb=f"درس‌ها › {course['title']}",
                    intro=message,
                    footer="داده‌ای به‌صورت محلی ساخته یا حدس زده نمی‌شود.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def schedule_menu(self, subject: str):
        try:
            _, _, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            rows = (
                (Button("امروز", self._cb("today")), Button("فردا", self._cb("tomorrow"))),
                (Button("۷ روز آینده", self._cb("week")),),
            ) + self._nav_rows()
            return ActionResult(
                semantic_screen(
                    "📅 برنامه",
                    "schedule_menu",
                    intro="برنامه بر اساس منطقه زمانی فضای آموزشی نمایش داده می‌شود.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def _schedule_for_date(self, subject: str, workspace_id: str, target_date: date):
        value = target_date.isoformat()
        projection = self.backend.schedule(
            self.platform, subject, workspace_id, value, value, 100, None
        )
        timezone_name = str(projection.get("timezone") or "UTC")
        return projection, timezone_name

    @staticmethod
    def _schedule_sections(items, timezone_name: str) -> tuple[SemanticSection, ...]:
        sections = []
        for item in items:
            start = format_time(item.get("starts_at"), timezone_name)
            item_title = truncate_text(
                item.get("title") or item.get("course_title") or "رویداد", 100
            )
            location = truncate_text(item.get("location_text") or "", 100)
            course = truncate_text(item.get("course_title") or "", 80)
            facts = [f"زمان: {start}"]
            if course and course != item_title:
                facts.append(f"درس: {course}")
            if location:
                facts.append(f"مکان: {location}")
            sections.append(SemanticSection(title=item_title, items=tuple(facts)))
        return tuple(sections)

    def day_schedule(self, subject: str, offset: int):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            now_utc = datetime.now(timezone.utc)
            guessed = now_utc.date() + timedelta(days=offset)
            projection, timezone_name = self._schedule_for_date(subject, workspace_id, guessed)
            try:
                local_date = now_utc.astimezone(ZoneInfo(timezone_name)).date() + timedelta(days=offset)
            except Exception:
                local_date = guessed
            if local_date != guessed:
                projection, timezone_name = self._schedule_for_date(subject, workspace_id, local_date)
            items = projection.get("items") or []
            day_label = "امروز" if offset == 0 else "فردا"
            title = f"📅 برنامه {day_label}"
            intro = "" if items else f"برای {day_label} برنامه‌ای ثبت نشده است."
            rows = self._nav_rows(back_action="schedule", back_label="‹ برنامه")
            return ActionResult(
                semantic_screen(
                    title,
                    "schedule",
                    breadcrumb=f"برنامه › {day_label}",
                    intro=intro,
                    sections=self._schedule_sections(items[:30], timezone_name),
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def week_schedule(self, subject: str, cursor: str | None = None, history: list[str] | None = None):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            _, timezone_name, local_today = self._today_projection(subject, workspace_id)
            projection = self.backend.schedule(
                self.platform,
                subject,
                workspace_id,
                local_today.isoformat(),
                (local_today + timedelta(days=6)).isoformat(),
                20,
                cursor,
            )
            items = projection.get("items") or []
            next_cursor = self._clean_cursor(projection.get("next_cursor"))
            history = [str(value) for value in (history or []) if value == "" or CURSOR_RE.fullmatch(str(value))]
            rows: tuple[tuple[Button, ...], ...] = ()
            page_number = len(history) + 1
            pager: list[Button] = []
            if history:
                previous = history[-1] or None
                prev_ref = self._route_callback(
                    subject,
                    "schedule_page",
                    "schp",
                    {"cursor": previous or "", "history": history[:-1]},
                )
                pager.append(Button("‹ قبلی", prev_ref))
            if next_cursor:
                next_ref = self._route_callback(
                    subject,
                    "schedule_page",
                    "schp",
                    {"cursor": next_cursor, "history": history + [cursor or ""]},
                )
                pager.append(Button("بعدی ›", next_ref))
            if pager:
                rows += (tuple(pager),)
            rows += self._nav_rows(back_action="schedule", back_label="‹ برنامه")
            return ActionResult(
                semantic_screen(
                    "📅 ۷ روز آینده",
                    "schedule_week",
                    breadcrumb="برنامه › ۷ روز آینده",
                    intro="" if items else "در این بازه برنامه‌ای ثبت نشده است.",
                    sections=self._schedule_sections(items, timezone_name),
                    pagination=f"صفحه {format_human_number(page_number)}",
                    rows=rows,
                    edit=bool(cursor or history),
                )
            )
        except Exception as exc:
            return self._error(exc)

    @staticmethod
    def _grade_lines(items, *, include_course: bool = True) -> tuple[str, ...]:
        display = []
        for item in items:
            name = truncate_text(
                item.get("item_title") or item.get("gradebook_title") or "نمره", 90
            )
            course = truncate_text(item.get("course_title") or "", 70)
            score = format_score(item.get("score"))
            maximum = item.get("max_score")
            rendered = f"{name}: {score}"
            if maximum is not None:
                rendered += f" از {format_score(maximum)}"
            if include_course and course:
                rendered = f"{course} · {rendered}"
            display.append(rendered)
        return tuple(display)

    def grades(
        self,
        subject: str,
        cursor: str | None = None,
        history: list[str] | None = None,
    ):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            projection = self.backend.grades(
                self.platform, subject, workspace_id, 20, cursor
            )
            items = projection.get("items") or []
            next_cursor = self._clean_cursor(projection.get("next_cursor"))
            history = [str(value) for value in (history or []) if value == "" or CURSOR_RE.fullmatch(str(value))]
            rows: tuple[tuple[Button, ...], ...] = ()
            pager: list[Button] = []
            if history:
                prev_ref = self._route_callback(
                    subject,
                    "grades_page",
                    "grdp",
                    {"cursor": history[-1], "history": history[:-1]},
                )
                pager.append(Button("‹ قبلی", prev_ref))
            if next_cursor:
                next_ref = self._route_callback(
                    subject,
                    "grades_page",
                    "grdp",
                    {"cursor": next_cursor, "history": history + [cursor or ""]},
                )
                pager.append(Button("بعدی ›", next_ref))
            if pager:
                rows += (tuple(pager),)
            rows += self._nav_rows(back_action="more", back_label="‹ بیشتر")
            return ActionResult(
                semantic_screen(
                    "🎓 نمرات",
                    "grades",
                    breadcrumb="بیشتر › نمرات",
                    intro="" if items else "نمره منتشرشده‌ای برای شما پیدا نشد.",
                    list_items=self._grade_lines(items),
                    pagination=f"صفحه {format_human_number(len(history) + 1)}",
                    footer="معدل محاسبه نمی‌شود مگر backend داده کامل و policy معتبر ارائه کند.",
                    rows=rows,
                    edit=bool(cursor or history),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def announcements(
        self,
        subject: str,
        cursor: str | None = None,
        history: list[str] | None = None,
    ):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            projection = self.backend.announcements(
                self.platform, subject, workspace_id, 10, cursor
            )
            items = projection.get("items") or []
            sections = []
            for item in items:
                title = truncate_text(item.get("title") or "اطلاعیه", 120)
                body = truncate_text(str(item.get("body") or "").strip(), 700)
                published = (
                    item.get("published_at")
                    or item.get("publishedAt")
                    or item.get("effective_at")
                    or item.get("effectiveAt")
                    or item.get("created_at")
                    or item.get("createdAt")
                )
                time_text = format_datetime(published)
                section_items = (f"زمان: {time_text}",) if time_text else ()
                sections.append(SemanticSection(title=title, body=body, items=section_items))
            next_cursor = self._clean_cursor(projection.get("next_cursor"))
            history = [str(value) for value in (history or []) if value == "" or CURSOR_RE.fullmatch(str(value))]
            rows: tuple[tuple[Button, ...], ...] = ()
            pager: list[Button] = []
            if history:
                prev_ref = self._route_callback(
                    subject,
                    "ann_page",
                    "annp",
                    {"cursor": history[-1], "history": history[:-1]},
                )
                pager.append(Button("‹ قبلی", prev_ref))
            if next_cursor:
                next_ref = self._route_callback(
                    subject,
                    "ann_page",
                    "annp",
                    {"cursor": next_cursor, "history": history + [cursor or ""]},
                )
                pager.append(Button("بعدی ›", next_ref))
            if pager:
                rows += (tuple(pager),)
            rows += self._nav_rows(back_action="notifs", back_label="‹ اعلان‌ها")
            return ActionResult(
                semantic_screen(
                    "📢 اطلاعیه‌ها",
                    "announcements",
                    breadcrumb="اعلان‌ها › اطلاعیه‌ها",
                    intro="" if items else "اطلاعیه منتشرشده‌ای پیدا نشد.",
                    sections=sections,
                    pagination=f"صفحه {format_human_number(len(history) + 1)}",
                    rows=rows,
                    edit=bool(cursor or history),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def notifications(self, subject: str):
        try:
            _, _, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            rows = (
                (Button("📢 اطلاعیه‌ها", self._cb("ann")),),
            )
            rows += self._web_row("🌐 اعلان‌های کامل در فانوس")
            rows += self._nav_rows()
            return ActionResult(
                semantic_screen(
                    "🔔 اعلان‌ها",
                    "notifications",
                    intro="اعلان‌های شخصی از مسیر canonical فانوس به پیام‌رسان تحویل می‌شوند.",
                    sections=(
                        SemanticSection(
                            "📢 اطلاعیه‌ها",
                            "پیام‌های عمومی فضای آموزشی و اطلاعیه‌های منتشرشده.",
                        ),
                        SemanticSection(
                            "🔔 اعلان‌های شخصی",
                            "تاریخچهٔ شخصی در bot-safe API فعلی وجود ندارد؛ NotificationPump فقط worker تحویل و receipt است.",
                        ),
                    ),
                    footer="برای جلوگیری از ساخت inbox جعلی، تاریخچه محلی نمایش داده نمی‌شود.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def resources(
        self,
        subject: str,
        cursor: str | None = None,
        history: list[str] | None = None,
    ):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            projection = self.backend.resources(
                self.platform, subject, workspace_id, 12, cursor
            )
            items = projection.get("items") or []
            rows: tuple[tuple[Button, ...], ...] = ()
            display = []
            for item in items:
                rid = str(item.get("resource_id") or "")
                title = truncate_text(item.get("title") or "منبع", 100)
                type_label = resource_type_label(
                    item.get("type_key")
                    or item.get("resource_type")
                    or item.get("type")
                    or item.get("kind")
                )
                course = truncate_text(item.get("course_title") or "", 70)
                display.append(
                    f"{title} · {type_label}" + (f" · {course}" if course else "")
                )
                if is_uuid(rid):
                    rows += ((Button(truncate_text(title, 38), self._cb("resdetail", rid)),),)

            next_cursor = self._clean_cursor(projection.get("next_cursor"))
            history = [str(value) for value in (history or []) if value == "" or CURSOR_RE.fullmatch(str(value))]
            pager: list[Button] = []
            if history:
                prev_ref = self._route_callback(
                    subject,
                    "resources_page",
                    "resp",
                    {"cursor": history[-1], "history": history[:-1]},
                )
                pager.append(Button("‹ قبلی", prev_ref))
            if next_cursor:
                next_ref = self._route_callback(
                    subject,
                    "resources_page",
                    "resp",
                    {"cursor": next_cursor, "history": history + [cursor or ""]},
                )
                pager.append(Button("بعدی ›", next_ref))
            if pager:
                rows += (tuple(pager),)
            rows += self._nav_rows(back_action="more", back_label="‹ بیشتر")
            return ActionResult(
                semantic_screen(
                    "📚 منابع",
                    "resources",
                    breadcrumb="بیشتر › منابع",
                    intro="" if items else "منبع قابل‌دسترسی‌ای پیدا نشد.",
                    list_items=display,
                    pagination=f"صفحه {format_human_number(len(history) + 1)}",
                    rows=rows,
                    edit=bool(cursor or history),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def _find_resource(self, subject: str, workspace_id: str, resource_id: str) -> dict | None:
        if not is_uuid(resource_id):
            return None
        for item in self._resource_items(subject, workspace_id):
            if str(item.get("resource_id") or "") == resource_id:
                return item
        return None

    def resource_detail(self, subject: str, resource_id: str):
        try:
            _, workspace_id, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            item = self._find_resource(subject, workspace_id, resource_id)
            if not item:
                return ActionResult(
                    warning_screen(
                        "این منبع دیگر در فهرست مجاز شما نیست. فهرست منابع را تازه باز کنید.",
                        title="📚 منبع در دسترس نیست",
                        kind="resource_unavailable",
                        rows=((Button("‹ منابع", self._cb("resources")),),) + self._nav_rows(),
                    )
                )
            title = truncate_text(item.get("title") or "منبع", 110)
            type_label = resource_type_label(
                item.get("type_key")
                or item.get("resource_type")
                or item.get("type")
                or item.get("kind")
            )
            facts = [("نوع", type_label)]
            course = truncate_text(item.get("course_title") or "", 80)
            if course:
                facts.append(("درس", course))
            topic = truncate_text(item.get("topic") or "", 100)
            if topic:
                facts.append(("موضوع", topic))
            professor = truncate_text(item.get("professor_name") or "", 80)
            if professor:
                facts.append(("استاد", professor))
            facts.append(("نسخه", "نسخه جاری مجاز"))
            facts.append(("دسترسی", "قابل دریافت" if item.get("delivery_supported") else "نمایش اطلاعات"))
            rows: tuple[tuple[Button, ...], ...] = ()
            if item.get("delivery_supported"):
                rows += ((Button("🔒 دریافت امن", self._cb("resource", resource_id)),),)
            rows += self._nav_rows(back_action="resources", back_label="‹ منابع")
            return ActionResult(
                semantic_screen(
                    f"📚 {title}",
                    "resource_detail",
                    breadcrumb="منابع › جزئیات",
                    intro=truncate_text(item.get("description") or "", 900),
                    facts=facts,
                    footer="شناسه فایل، storage key و مسیر داخلی نمایش داده نمی‌شود.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def assessments(self, subject: str):
        try:
            _, _, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            rows = self._web_row() + self._nav_rows(back_action="more", back_label="‹ بیشتر")
            return ActionResult(
                semantic_screen(
                    "📝 آزمون‌ها",
                    "assessments_gap",
                    severity="warning",
                    breadcrumb="بیشتر › آزمون‌ها",
                    intro="projection امن فهرست/attempt/result آزمون برای ربات هنوز در internal API وجود ندارد.",
                    footer="تا زمان اضافه‌شدن قرارداد backend، پاسخ، امتیاز یا وضعیت آزمون به‌صورت محلی ساخته نمی‌شود.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def payments(self, subject: str):
        try:
            _, selected = self._selected(subject)
            if not selected:
                return ActionResult(
                    warning_screen(
                        "ابتدا یک فضای آموزشی فعال انتخاب کنید.",
                        title="💳 خرید و دسترسی",
                        kind="payments",
                        rows=((Button("🏫 فضای آموزشی", self._cb("workspaces")),),) + self._nav_rows(),
                    )
                )
            rows = self._web_row("🌐 خرید و دسترسی در فانوس")
            rows += self._nav_rows(back_action="more", back_label="‹ بیشتر")
            return ActionResult(
                semantic_screen(
                    "💳 خرید و دسترسی",
                    "payments",
                    breadcrumb="بیشتر › خرید و دسترسی",
                    intro="فهرست محصول bot-safe هنوز در backend وجود ندارد؛ خرید عادی از رابط فانوس انجام می‌شود.",
                    footer="پرداخت و دسترسی فقط از وضعیت تأییدشده backend نمایش داده می‌شود.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def create_order(self, subject: str, product_id: str, idempotency_key: str | None = None):
        """Backward-compatible technical path; never advertised as product UX."""
        try:
            _, workspace_id = self._selected(subject)
            if not workspace_id:
                return ActionResult(
                    warning_screen(
                        "ابتدا یک فضای آموزشی فعال انتخاب کنید.",
                        title="💳 خرید و دسترسی",
                        kind="payment_order",
                        rows=self._nav_rows(),
                    )
                )
            if not product_id:
                return ActionResult(
                    warning_screen(
                        "برای خرید عادی از بخش «خرید و دسترسی» وارد فانوس شوید.",
                        title="💳 خرید و دسترسی",
                        kind="payment_order",
                        rows=self._web_row() + self._nav_rows(),
                    )
                )
            idem = idempotency_key or "bot-order-" + secrets.token_hex(12)
            if not SAFE_IDEM.fullmatch(idem):
                return ActionResult(
                    error_screen(
                        "درخواست خرید معتبر نیست.",
                        kind="payment_order",
                        rows=self._nav_rows(),
                    )
                )
            order = self.backend.create_order(
                self.platform, subject, workspace_id, product_id, idem
            )
            status = order_status_label(order.get("status"))
            title = truncate_text(order.get("title") or "سفارش فانوس", 100)
            rows: tuple[tuple[Button, ...], ...] = ()
            url = order.get("payment_url")
            if isinstance(url, str) and url.startswith("https://"):
                rows += ((Button("ادامه برای پرداخت", url=url),),)
            rows += self._nav_rows(back_action="payments", back_label="‹ خرید و دسترسی")
            return ActionResult(
                semantic_screen(
                    "💳 سفارش",
                    "payment_order",
                    facts=(
                        ("عنوان", title),
                        ("مبلغ", format_money(order.get("amount_minor"), order.get("currency"))),
                        ("وضعیت", status),
                    ),
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def order_status(self, subject: str, order_id: str):
        try:
            _, workspace_id = self._selected(subject)
            if not workspace_id:
                return ActionResult(
                    warning_screen(
                        "ابتدا یک فضای آموزشی فعال انتخاب کنید.",
                        title="💳 خرید و دسترسی",
                        kind="payment_status",
                        rows=self._nav_rows(),
                    )
                )
            order = self.backend.order_status(
                self.platform, subject, workspace_id, order_id
            )
            entitlement = order.get("entitlement") or {}
            status = order_status_label(order.get("status"))
            severity = (
                "success"
                if str(order.get("status") or "").lower() in {"paid", "succeeded"}
                else "info"
            )
            return ActionResult(
                semantic_screen(
                    "💳 وضعیت سفارش",
                    "payment_status",
                    severity=severity,
                    facts=(
                        ("وضعیت پرداخت", status),
                        ("دسترسی", entitlement_label(entitlement.get("granted"))),
                    ),
                    rows=self._nav_rows(back_action="payments", back_label="‹ خرید و دسترسی"),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def account(self, subject: str):
        try:
            projection, selected = self._selected(subject)
            rows = self._web_row()
            rows += (
                (Button("🏫 تغییر فضای آموزشی", self._cb("workspaces")),),
                (Button("قطع اتصال پیام‌رسان", self._cb("unlinkask")),),
            )
            rows += self._nav_rows(back_action="more", back_label="‹ بیشتر")
            return ActionResult(
                semantic_screen(
                    "👤 حساب",
                    "account",
                    breadcrumb="بیشتر › حساب",
                    facts=(
                        ("پیام‌رسان", platform_label(self.platform)),
                        ("وضعیت اتصال", "متصل"),
                        (
                            "تعداد فضای آموزشی",
                            format_human_number(len(projection.get("workspaces") or [])),
                        ),
                        ("فضای فعال", self._selected_workspace_label(projection, selected)),
                    ),
                    footer="قطع اتصال فقط همین پیام‌رسان را جدا می‌کند؛ حساب اصلی فانوس حذف نمی‌شود.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def workspaces(self, subject: str):
        try:
            projection = self._workspace_projection(subject)
            workspaces = projection.get("workspaces") or []
            selected = projection.get("selected_workspace_id")
            if not workspaces:
                return ActionResult(
                    semantic_screen(
                        "🏫 فضای آموزشی",
                        "workspace_empty",
                        severity="warning",
                        intro="فضای آموزشی قابل‌دسترسی برای این حساب پیدا نشد.",
                        rows=self._nav_rows(back_action="more", back_label="‹ بیشتر"),
                    )
                )
            rows: tuple[tuple[Button, ...], ...] = ()
            items = []
            for workspace in workspaces:
                wid = str(workspace.get("id") or "")
                if not is_uuid(wid):
                    continue
                label = self._workspace_label(workspace)
                marker = "✓ " if wid == selected else ""
                items.append(marker + label)
                rows += ((Button(marker + label, self._cb("ws", wid)),),)
            if not rows:
                return ActionResult(
                    semantic_screen(
                        "🏫 فضای آموزشی",
                        "workspace_empty",
                        severity="warning",
                        intro="فضای آموزشی قابل انتخابی پیدا نشد.",
                        rows=self._nav_rows(),
                    )
                )
            rows += self._nav_rows(back_action="more", back_label="‹ بیشتر")
            return ActionResult(
                semantic_screen(
                    "🏫 فضای آموزشی",
                    "workspace_list",
                    breadcrumb="بیشتر › فضای آموزشی",
                    intro="فضای آموزشی فعال را انتخاب کنید.",
                    list_items=items,
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def select_workspace(self, subject: str, workspace_id: str):
        if not is_uuid(workspace_id):
            return self._expired_route()
        try:
            self.backend.select_workspace(self.platform, subject, workspace_id)
            return self.home(subject, "فضای آموزشی فعال تغییر کرد.")
        except Exception as exc:
            return self._error(exc)

    def _failed_receipt(self, workspace_id: str, issuance_id: str, code: str):
        try:
            self.backend.delivery_receipt(
                self.platform,
                workspace_id,
                issuance_id,
                "bot-delivery-" + secrets.token_hex(12),
                "failed",
                None,
                code,
            )
        except Exception:
            pass

    @staticmethod
    def _protected_text(content: object) -> str:
        if isinstance(content, str):
            return content
        if isinstance(content, dict):
            for key in ("text", "body", "content"):
                value = content.get(key)
                if isinstance(value, str) and value.strip():
                    return value
        return "✅ محتوای محافظت‌شده آماده است."

    def protected_resource(self, subject: str, resource_id: str):
        if not is_uuid(resource_id):
            return self._expired_route()
        try:
            _, workspace_id = self._selected(subject)
            if not workspace_id:
                return ActionResult(
                    warning_screen(
                        "ابتدا یک فضای آموزشی فعال انتخاب کنید.",
                        title="🔒 محتوای محافظت‌شده",
                        kind="protected_delivery",
                        rows=self._nav_rows(),
                    )
                )
            issued = self.backend.delivery_issue(
                self.platform, subject, workspace_id, resource_id
            )
            consumed = self.backend.delivery_consume(
                self.platform, subject, workspace_id, issued["delivery_token"]
            )
            issuance = str(
                consumed.get("issuance_id") or issued.get("issuance_id") or ""
            )
            forward = bool(consumed.get("forward_protection_required"))
            if forward and self.platform == "bale":
                self._failed_receipt(
                    workspace_id, issuance, "unsupported_forward_protection"
                )
                return ActionResult(
                    warning_screen(
                        "این منبع باید با محدودیت بازنشر ارسال شود، اما این قابلیت در رابط رسمی بله در دسترس نیست. ارسال انجام نشد.",
                        title="🔒 ارسال محافظت‌شده در بله",
                        kind="protected_delivery_unsupported",
                        rows=self._nav_rows(back_action="resources", back_label="‹ منابع"),
                    )
                )
            if consumed.get("object_id"):
                enqueued = self.backend.media_enqueue(
                    workspace_id,
                    issuance,
                    self.config.protected_renderer_version,
                    {},
                )
                job_id = str(enqueued.get("job_id") or "")
                if not is_uuid(job_id):
                    raise RuntimeError("protected media job id missing")
                self.media_state.remember(job_id, subject, workspace_id, issuance)
                return ActionResult(
                    semantic_screen(
                        "🔒 در حال آماده‌سازی نسخه محافظت‌شده",
                        "protected_delivery_preparing",
                        intro="درخواست ثبت شد. برای بررسی آماده‌شدن نسخه، دکمه زیر را بزنید.",
                        rows=(
                            (Button("🔄 بررسی آماده‌شدن", self._cb("pm", job_id)),),
                            (Button("🏠 خانه", self._cb("home")),),
                        ),
                    )
                )
            text = self._protected_text(consumed.get("content"))
            receipt = DeliveryReceiptContext(
                workspace_id,
                issuance,
                "bot-delivery-" + secrets.token_hex(12),
            )
            return ActionResult(
                semantic_screen(
                    "🔒 محتوای محافظت‌شده",
                    "protected_delivery_ready",
                    severity="success",
                    intro="محتوا آماده است.",
                    protect_content=forward,
                    text=text,
                ),
                receipt,
            )
        except Exception as exc:
            return self._error(exc)

    def protected_media_ready(self, subject: str, job_id: str):
        correlation = self.media_state.get(job_id, subject)
        if not correlation:
            return ActionResult(
                warning_screen(
                    "درخواست فایل منقضی شده یا برای این حساب نیست. دوباره از بخش منابع شروع کنید.",
                    title="🔒 نسخه محافظت‌شده",
                    kind="protected_delivery_expired",
                    rows=((Button("📚 منابع", self._cb("resources")),),) + self._nav_rows(),
                )
            )
        try:
            _, selected = self._selected(subject)
            if selected != correlation["workspace_id"]:
                return ActionResult(
                    warning_screen(
                        "برای دریافت این فایل همان فضای آموزشی قبلی را فعال کنید.",
                        title="🏫 فضای آموزشی نادرست",
                        kind="protected_delivery_workspace",
                        rows=((Button("🏫 فضای آموزشی", self._cb("workspaces")),),) + self._nav_rows(),
                    )
                )
            try:
                issued = self.backend.media_derivative_issue(
                    self.platform, subject, selected, job_id
                )
            except FanoosApiError as exc:
                if (
                    exc.code
                    in {
                        "protected_media_artifact_unavailable",
                        "protected_media_job_not_found",
                    }
                    and exc.status in {404, 409}
                ):
                    return ActionResult(
                        semantic_screen(
                            "🔒 نسخه هنوز آماده نشده است",
                            "protected_delivery_preparing",
                            intro="هنوز نسخه قابل دریافت نیست.",
                            rows=(
                                (Button("🔄 بررسی دوباره", self._cb("pm", job_id)),),
                                (Button("🏠 خانه", self._cb("home")),),
                            ),
                        )
                    )
                raise
            size = int(issued.get("size") or 0)
            max_bytes = 50 * 1024 * 1024
            if size < 5 or size > max_bytes:
                self._failed_receipt(
                    selected, correlation["issuance_id"], "file_too_large"
                )
                self.media_state.forget(job_id, subject)
                return ActionResult(
                    warning_screen(
                        "نسخه آماده است، اما اندازه فایل از سقف ارسال مستقیم این پیام‌رسان بیشتر است.",
                        title="⚠️ ارسال فایل ممکن نیست",
                        kind="protected_delivery_size",
                        rows=self._nav_rows(),
                    )
                )
            data = self.backend.media_derivative_redeem(
                self.platform,
                subject,
                selected,
                str(issued.get("artifact_capability") or ""),
                max_bytes,
            )
            receipt = DeliveryReceiptContext(
                selected,
                correlation["issuance_id"],
                "bot-delivery-media:" + job_id,
            )
            document = DocumentPayload(
                data,
                "fanoos-protected.pdf",
                "🔒 نسخه محافظت‌شده فانوس",
                True,
            )
            return ActionResult(
                semantic_screen(
                    "✅ نسخه محافظت‌شده آماده است",
                    "protected_delivery_ready",
                    severity="success",
                    protect_content=True,
                ),
                receipt,
                document,
                {"media_job_id": job_id},
            )
        except Exception as exc:
            return self._error(exc)

    def management(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return ActionResult(
                warning_screen(
                    "بخش مدیریت سرور فقط در گفت‌وگوی خصوصی تلگرام و پس از مجوز canonical فعال است.",
                    title="⚙️ مدیریت",
                    kind="management_unavailable",
                    rows=self._nav_rows(back_action="more", back_label="‹ بیشتر"),
                )
            )
        if not self.config.deployment_target_key:
            return ActionResult(
                warning_screen(
                    "کنترل به‌روزرسانی برای این محیط تنظیم نشده است.",
                    title="⚙️ مدیریت",
                    kind="management_unavailable",
                    rows=self._nav_rows(back_action="more", back_label="‹ بیشتر"),
                )
            )
        try:
            overview = self.backend.deployment_overview(
                subject, self.config.deployment_target_key
            )
            if not overview.get("can_manage_deployments"):
                return ActionResult(
                    error_screen(
                        "اجازه مدیریت سرور را ندارید.",
                        title="⚙️ مدیریت",
                        kind="management_denied",
                        rows=self._nav_rows(back_action="more", back_label="‹ بیشتر"),
                    )
                )
            rows = ((Button("🔄 به‌روزرسانی سرور", self._cb("update")),),)
            if self.state.latest_deployment(subject):
                rows += ((Button("وضعیت آخرین به‌روزرسانی", self._cb("updlast")),),)
            rows += self._nav_rows(back_action="more", back_label="‹ بیشتر")
            return ActionResult(
                semantic_screen(
                    "⚙️ مدیریت",
                    "management",
                    breadcrumb="بیشتر › مدیریت",
                    intro="فقط عملیات‌هایی نمایش داده می‌شوند که backend مجوز آن‌ها را برای این حساب تأیید کرده است.",
                    rows=rows,
                )
            )
        except Exception as exc:
            return self._error(exc)

    def update_begin(self, subject: str, private: bool):
        if self.platform != "telegram":
            return ActionResult(
                warning_screen(
                    "به‌روزرسانی سرور از این پیام‌رسان فعال نیست.",
                    title="🔄 به‌روزرسانی سرور",
                    kind="deployment",
                    rows=self._nav_rows(),
                )
            )
        if not private:
            return ActionResult(
                warning_screen(
                    "این عملیات فقط در گفت‌وگوی خصوصی تلگرام انجام می‌شود.",
                    title="🔄 به‌روزرسانی سرور",
                    kind="deployment",
                    rows=self._nav_rows(),
                )
            )
        if not self.config.deployment_target_key:
            return ActionResult(
                error_screen(
                    "هدف به‌روزرسانی برای این محیط تنظیم نشده است.",
                    title="🔄 به‌روزرسانی سرور",
                    kind="deployment",
                    rows=self._nav_rows(),
                )
            )
        try:
            overview = self.backend.deployment_overview(
                subject, self.config.deployment_target_key
            )
            if not overview.get("can_manage_deployments"):
                return ActionResult(
                    error_screen(
                        "اجازه مدیریت به‌روزرسانی سرور را ندارید.",
                        title="🔄 به‌روزرسانی سرور",
                        kind="deployment",
                        rows=self._nav_rows(),
                    )
                )
            current = str(overview.get("current_release_sha") or "")
            candidate = str(overview.get("candidate_sha") or "")
            available = overview.get("update_available")
            health = overview.get("health") or {}
            if available is True:
                intro = "نسخه جدید موجود است."
            elif available is False:
                intro = "نسخه جدیدی مشاهده نشده است."
            else:
                intro = "وضعیت نسخه جدید هنوز قطعی نیست."
            facts = [("وضعیت فعلی", health_status_label(health.get("status")))]
            current_short = short_sha(current)
            candidate_short = short_sha(candidate)
            if current_short:
                facts.append(("نسخه فعلی (SHA)", current_short))
            if candidate_short:
                facts.append(("نسخه جدید (SHA)", candidate_short))
            confirmation = self.state.create_confirmation(
                subject, self.config.deployment_target_key
            )
            return ActionResult(
                semantic_screen(
                    "🔄 به‌روزرسانی سرور",
                    "deployment_confirmation",
                    severity="warning",
                    breadcrumb="بیشتر › مدیریت › به‌روزرسانی سرور",
                    intro=intro,
                    facts=facts,
                    footer="⚠️ اجرای به‌روزرسانی ممکن است سرویس‌ها را راه‌اندازی مجدد کند.",
                    rows=(
                        (
                            Button(
                                "✅ تأیید به‌روزرسانی",
                                self._cb("upd_c", confirmation["ref"]),
                            ),
                            Button("لغو", self._cb("upd_x", confirmation["ref"])),
                        ),
                        (Button("🏠 خانه", self._cb("home")),),
                    ),
                )
            )
        except Exception as exc:
            return self._error(exc)

    def update_confirm(self, subject: str, private: bool, ref: str):
        if self.platform != "telegram" or not private:
            return ActionResult(
                error_screen(
                    "درخواست معتبر نیست.",
                    kind="deployment",
                    rows=self._nav_rows(),
                )
            )
        confirmation = self.state.confirmation(ref, subject)
        if not confirmation:
            return ActionResult(
                warning_screen(
                    "تأیید منقضی، لغوشده یا قبلاً استفاده شده است.",
                    title="🔄 به‌روزرسانی سرور",
                    kind="deployment",
                    rows=self._nav_rows(),
                )
            )
        try:
            result = self.backend.request_deployment(
                subject,
                confirmation["target_key"],
                confirmation["idempotency_key"],
            )
            request_id = str(result["request_id"])
            self.state.record_deployment(subject, request_id)
            self.state.complete_confirmation(ref, subject)
            return self._deployment_screen(result)
        except Exception as exc:
            return self._error(exc)

    def update_cancel(self, subject: str, ref: str):
        self.state.cancel_confirmation(ref, subject)
        return ActionResult(
            semantic_screen(
                "🔄 به‌روزرسانی سرور",
                "deployment_cancelled",
                intro="درخواست به‌روزرسانی لغو شد.",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def update_status(
        self,
        subject: str,
        private: bool,
        request_id: str | None = None,
    ):
        if self.platform != "telegram" or not private:
            return ActionResult(
                warning_screen(
                    "این عملیات فقط در گفت‌وگوی خصوصی تلگرام فعال است.",
                    title="🔄 به‌روزرسانی سرور",
                    kind="deployment",
                    rows=self._nav_rows(),
                )
            )
        request_id = request_id or self.state.latest_deployment(subject)
        if not request_id:
            return ActionResult(
                semantic_screen(
                    "🔄 به‌روزرسانی سرور",
                    "deployment_empty",
                    intro="درخواست به‌روزرسانی ثبت‌شده‌ای پیدا نشد.",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        if not is_uuid(request_id):
            return self._expired_route()
        try:
            return self._deployment_screen(
                self.backend.deployment_status(subject, request_id)
            )
        except Exception as exc:
            return self._error(exc)

    def _deployment_screen(self, deployment: dict):
        state = str(deployment.get("state") or "UNKNOWN").upper()
        state_label = deployment_state_label(state)
        severity = (
            "success"
            if state == "SUCCEEDED"
            else "error"
            if state == "FAILED"
            else "warning"
            if state == "ROLLED_BACK"
            else "info"
        )
        facts = [("وضعیت فعلی", state_label)]
        candidate = short_sha(deployment.get("candidate_sha"))
        if candidate:
            facts.append(("نسخه (SHA)", candidate))
        if deployment.get("failure_code"):
            facts.append(
                (
                    "نتیجه",
                    "به‌روزرسانی کامل نشد. جزئیات در گزارش مدیریتی سرور قابل بررسی است.",
                )
            )
        request_id = str(deployment.get("request_id") or "")
        rows: tuple[tuple[Button, ...], ...] = ()
        if is_uuid(request_id):
            rows += ((Button("🔄 تازه‌سازی وضعیت", self._cb("upd_s", request_id)),),)
        rows += self._nav_rows(back_action="manage", back_label="‹ مدیریت")
        return ActionResult(
            semantic_screen(
                "🔄 به‌روزرسانی سرور",
                "deployment_status",
                severity=severity,
                breadcrumb="بیشتر › مدیریت › به‌روزرسانی سرور",
                facts=facts,
                rows=rows,
                edit=True,
            )
        )

    def callback(self, subject: str, private: bool, value: str):
        try:
            action, ref = CallbackCodec.decode(value)
        except Exception:
            return ActionResult(
                warning_screen(
                    "این دکمه معتبر نیست. از خانه دوباره وارد بخش موردنظر شوید.",
                    kind="callback_invalid",
                    rows=self._nav_rows(),
                )
            )

        if action == "home":
            return self.home(subject)
        if action == "more":
            return self.more(subject, private)
        if action == "courses":
            return self.courses(subject)
        if action == "course" and ref:
            return self.course_detail(subject, ref)
        if action == "csched" and ref:
            return self.course_schedule(subject, ref)
        if action == "cres" and ref:
            return self.course_resources(subject, ref)
        if action == "cgrades" and ref:
            return self.course_grades(subject, ref)
        if action == "cann" and ref:
            return self.course_announcements(subject, ref)
        if action == "cassess" and ref:
            return self.course_assessments(subject, ref)

        if action == "schedule":
            return self.schedule_menu(subject)
        if action == "today":
            return self.day_schedule(subject, 0)
        if action == "tomorrow":
            return self.day_schedule(subject, 1)
        if action == "week":
            return self.week_schedule(subject)
        if action == "schp" and ref:
            payload = self._route_payload(subject, ref, "schedule_page")
            return (
                self.week_schedule(
                    subject,
                    self._clean_cursor((payload or {}).get("cursor")),
                    (payload or {}).get("history") or [],
                )
                if payload
                else self._expired_route()
            )

        if action == "grades":
            return self.grades(subject)
        if action == "grdp" and ref:
            payload = self._route_payload(subject, ref, "grades_page")
            return (
                self.grades(
                    subject,
                    self._clean_cursor((payload or {}).get("cursor")),
                    (payload or {}).get("history") or [],
                )
                if payload
                else self._expired_route()
            )

        if action == "notifs":
            return self.notifications(subject)
        if action == "ann":
            return self.announcements(subject)
        if action == "annp" and ref:
            payload = self._route_payload(subject, ref, "ann_page")
            return (
                self.announcements(
                    subject,
                    self._clean_cursor((payload or {}).get("cursor")),
                    (payload or {}).get("history") or [],
                )
                if payload
                else self._expired_route()
            )

        if action == "resources":
            return self.resources(subject)
        if action == "resp" and ref:
            payload = self._route_payload(subject, ref, "resources_page")
            return (
                self.resources(
                    subject,
                    self._clean_cursor((payload or {}).get("cursor")),
                    (payload or {}).get("history") or [],
                )
                if payload
                else self._expired_route()
            )
        if action == "resdetail" and ref:
            return self.resource_detail(subject, ref)
        if action == "resource" and ref:
            return self.protected_resource(subject, ref)
        if action == "pm" and ref:
            return self.protected_media_ready(subject, ref)

        if action == "assess":
            return self.assessments(subject)
        if action == "payments":
            return self.payments(subject)
        if action == "account":
            return self.account(subject)
        if action == "unlinkask":
            return self.unlink_confirm(subject)
        if action == "unlink":
            return self.unlink(subject)
        if action == "workspaces":
            return self.workspaces(subject)
        if action == "ws" and ref:
            return self.select_workspace(subject, ref)

        if action == "manage":
            return self.management(subject, private)
        if action == "update":
            return self.update_begin(subject, private)
        if action == "updlast":
            return self.update_status(subject, private)
        if action == "upd_c" and ref:
            return self.update_confirm(subject, private, ref)
        if action == "upd_x" and ref:
            return self.update_cancel(subject, ref)
        if action == "upd_s" and ref:
            return self.update_status(subject, private, ref)

        return ActionResult(
            warning_screen(
                "این دکمه دیگر معتبر نیست. از خانه دوباره وارد بخش موردنظر شوید.",
                kind="callback_expired",
                rows=self._nav_rows(),
            )
        )

    @staticmethod
    def help():
        return ActionResult(
            semantic_screen(
                "ℹ️ راهنمای فانوس",
                "help",
                intro="کار عادی فانوس با دکمه‌ها و مسیرهای داخل محصول انجام می‌شود.",
                list_items=(
                    "/start — شروع و ورود به خانه",
                    "/home — بازگشت به خانه",
                    "/help — همین راهنما",
                ),
                footer="دستورهای فنی قدیمی برای سازگاری ممکن است همچنان پذیرفته شوند، اما بخشی از UX اصلی نیستند.",
            )
        )
