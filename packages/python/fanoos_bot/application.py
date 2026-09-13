from __future__ import annotations

import logging
import re
import secrets
from dataclasses import dataclass
from datetime import date, datetime, timedelta, timezone
from zoneinfo import ZoneInfo

from . import join_wizard as jw
from .api import FanoosApiError
from .callbacks import CallbackCodec
from .formatting import (
    format_datetime,
    format_human_number,
    format_money,
    format_score,
    format_time,
    from_persian_digits,
    humanize_slug,
    is_uuid,
    short_sha,
    to_persian_digits,
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
from .persian_datetime import format_jalali_date, parse_jalali_date_input
from .presentation import error_screen, semantic_screen, success_screen, warning_screen
from .product_ui import course_catalog, filter_course, find_course

UUID_RE = re.compile(r"^[0-9a-f-]{36}$", re.I)
START_RE = re.compile(r"^[A-Za-z0-9_-]{1,64}$")
SAFE_IDEM = re.compile(r"^[A-Za-z0-9._:-]{1,160}$")
CURSOR_RE = re.compile(r"^[A-Za-z0-9_-]{1,32}$")
COUNTRY_CODE_RE = re.compile(r"^[A-Za-z]{2}$")

# Class-identity wizard (owner-only "create a class" flow). Each step collects
# one free-text field of the identity ClassProvisioningService already
# accepts (contracts/openapi/internal-v1.yaml POST /classes); nothing here
# invents domain truth, it only prompts for what the backend requires.
_CLASS_STEP_PROMPTS: dict[str, tuple[str, str]] = {
    "country_name": ("نام کشور", "نام کشور دانشگاه را بنویسید."),
    "country_code": ("کد کشور", "کد دو حرفی کشور را بنویسید؛ برای مثال ایران: IR"),
    "province": ("استان", "نام استان دانشگاه را بنویسید."),
    "city": ("شهر", "نام شهر دانشگاه را بنویسید."),
    "institution": (
        "دانشگاه یا مؤسسه",
        "نام دانشگاه یا مؤسسه را بنویسید. اگر قبلاً ثبت شده باشد، همان استفاده می‌شود.",
    ),
    "faculty": ("دانشکده", "نام دانشکده را بنویسید."),
    "program": ("رشته تحصیلی", "نام رشته تحصیلی را بنویسید."),
    "degree_level": (
        "مقطع تحصیلی",
        "مقطع تحصیلی را بنویسید؛ مثلاً کارشناسی، کارشناسی‌ارشد یا دکترای حرفه‌ای.",
    ),
    "entry_year": ("سال ورود", "سال ورود را بنویسید؛ مثلاً ۱۴۰۲."),
    "class_name": (
        "نام نمایشی کلاس",
        "یک نام نمایشی برای این کلاس بنویسید؛ همین نام برای اعضا نمایش داده می‌شود.",
    ),
}
_CLASS_STEP_ORDER: tuple[str, ...] = (
    "country_name", "country_code", "province", "city", "institution",
    "faculty", "program", "degree_level", "entry_year", "class_name",
)
_CLASS_TEXT_LIMITS: dict[str, tuple[int, int]] = {
    "country_name": (1, 160), "province": (1, 160), "city": (1, 160),
    "institution": (1, 200), "faculty": (1, 200), "program": (1, 200),
    "degree_level": (1, 48), "class_name": (1, 200),
}


def _validate_class_text(value: str, min_len: int, max_len: int) -> tuple[str | None, str | None]:
    text = " ".join((value or "").strip().split())
    if len(text) < min_len or len(text) > max_len:
        return None, f"متن باید بین {to_persian_digits(min_len)} تا {to_persian_digits(max_len)} نویسه باشد."
    return text, None


def _validate_country_code(value: str) -> tuple[str | None, str | None]:
    code = (value or "").strip().upper()
    if not COUNTRY_CODE_RE.fullmatch(code):
        return None, "کد کشور باید دقیقاً دو حرف انگلیسی باشد؛ مثلاً IR."
    return code, None


def _validate_entry_year(value: str) -> tuple[str | None, str | None]:
    normalized = from_persian_digits(value or "").strip()
    if not normalized.isdigit() or not (1000 <= int(normalized) <= 9999):
        return None, "سال ورود را فقط با رقم و در بازه‌ای معتبر بنویسید؛ مثلاً ۱۴۰۲."
    return str(int(normalized)), None


_TERM_KEY_RE = re.compile(r"^[a-z0-9](?:[a-z0-9-]{0,62}[a-z0-9])?$")


def _validate_term_key(value: str) -> tuple[str | None, str | None]:
    normalized = re.sub(r"[^a-z0-9]+", "-", (value or "").strip().lower()).strip("-")
    if not normalized or not _TERM_KEY_RE.fullmatch(normalized):
        return None, "شناسه ترم را فقط با حروف انگلیسی و رقم بنویسید؛ مثلاً fall-1406."
    return normalized, None


def _validate_jalali_date(value: str) -> tuple[str | None, str | None]:
    iso = parse_jalali_date_input(value)
    if iso is None:
        return None, "تاریخ را به شکل سال/ماه/روز شمسی بنویسید؛ مثلاً ۱۴۰۶/۰۷/۰۱."
    return iso, None


def _class_field_validator(key: str):
    if key == "country_code":
        return _validate_country_code
    if key == "entry_year":
        return _validate_entry_year
    min_len, max_len = _CLASS_TEXT_LIMITS[key]
    return lambda value: _validate_class_text(value, min_len, max_len)


@dataclass(frozen=True)
class ApplicationConfig:
    web_base_url: str = ""
    deployment_target_key: str = ""
    protected_renderer_version: str = "fanoos-raster-v1"
    default_country_code: str = ""
    default_country_name: str = ""


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

    def _error(self, exc: Exception, *, subject: str | None = None, gated_label: str | None = None):
        # The backend, not the bot, decides who may reach class-internal material
        # (docs/product/01_FRONT_DOOR.md #9): a limited (self-joined, unapproved)
        # member gets 'forbidden' from AccessGate the same as anyone else without
        # the permission. When the caller tells us what was being attempted, turn
        # that specific denial into an explanation and a one-tap upgrade request
        # instead of the generic error screen -- the bot only ever hides a button
        # it already knows is unavailable; it never grants what the backend denied.
        if isinstance(exc, FanoosApiError) and exc.code == "forbidden" and subject and gated_label:
            return self._gated_action_result(subject, gated_label)
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

    def _gated_action_result(self, subject: str, action_label: str):
        rows = ((Button("✋ درخواست تأیید نماینده", self._cb("joinupgrade")),),) + self._nav_rows()
        return ActionResult(
            warning_screen(
                f"برای دسترسی به «{action_label}» باید نماینده کلاست تو رو تأیید کنه.",
                title="🔒 نیاز به تأیید نماینده",
                kind="join_upgrade_required",
                rows=rows,
            )
        )

    def join_wizard_request_upgrade(self, subject: str):
        """Idempotent per (person, class); the backend, not this method,
        enforces that only an active member may request this."""
        _, selected, blocked = self._workspace_or_result(subject)
        if blocked:
            return blocked
        try:
            self.backend.onboarding_upgrade_request(self.platform, subject, selected)
        except Exception as exc:
            return self._error(exc)
        return ActionResult(
            semantic_screen(
                "✋ درخواست تأیید",
                "join_upgrade_requested",
                severity="success",
                intro="درخواستت برای نماینده کلاس ثبت شد؛ وقتی تأییدت کنه، دسترسی کاملت فعال می‌شه.",
                rows=self._nav_rows(),
            )
        )

    def representative_requests(self, subject: str):
        """A representative's own approval queue for the currently selected
        workspace. membership.approve is checked server-side on every call
        (list, approve, decline); this method never decides authorization,
        it only renders whatever the backend already allowed."""
        try:
            _, selected, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            pending = self.backend.representative_requests_list(self.platform, subject, selected)
        except Exception as exc:
            return self._error(exc)
        items = [
            item for item in pending.get("items") or []
            if isinstance(item, dict) and is_uuid(str(item.get("request_id") or ""))
        ]
        if not items:
            return ActionResult(
                semantic_screen(
                    "📋 درخواست‌های عضویت",
                    "representative_requests_empty",
                    breadcrumb="بیشتر › درخواست‌های عضویت",
                    intro="درخواست در انتظار تأییدی برای این فضای آموزشی وجود ندارد.",
                    rows=self._nav_rows(back_action="more", back_label="‹ بیشتر"),
                )
            )
        rows = tuple(
            (
                Button(f"✅ تأیید «{name}»", self._cb("repappr", request_id)),
                Button("❌ رد", self._cb("repdecl", request_id)),
            )
            for item in items
            for request_id in (str(item["request_id"]),)
            for name in (truncate_text(str(item.get("display_name") or "دانشجو"), 30),)
        ) + self._nav_rows(back_action="more", back_label="‹ بیشتر")
        return ActionResult(
            semantic_screen(
                "📋 درخواست‌های عضویت",
                "representative_requests",
                breadcrumb="بیشتر › درخواست‌های عضویت",
                intro="درخواست‌های در انتظار تأیید برای این فضای آموزشی:",
                rows=rows,
            )
        )

    def representative_request_approve(self, subject: str, request_id: str):
        return self._representative_request_decision(subject, request_id, approve=True)

    def representative_request_decline(self, subject: str, request_id: str):
        return self._representative_request_decision(subject, request_id, approve=False)

    def _representative_request_decision(self, subject: str, request_id: str, *, approve: bool):
        if not is_uuid(request_id):
            return self._expired_route()
        try:
            _, selected, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            if approve:
                self.backend.representative_requests_approve(self.platform, subject, selected, request_id)
            else:
                self.backend.representative_requests_decline(self.platform, subject, selected, request_id)
        except Exception as exc:
            logging.error(
                "representative request decision failed approve=%s type=%s message=%s",
                approve,
                type(exc).__name__,
                exc,
            )
            return self._error(exc)
        return self.representative_requests(subject)

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
            can_manage = False
            if (
                self.platform == "telegram"
                and private
                and self.config.deployment_target_key
            ):
                try:
                    overview = self.backend.deployment_overview(
                        subject, self.config.deployment_target_key
                    )
                    can_manage = overview.get("can_manage_deployments") is True
                except Exception as exc:
                    logging.warning(
                        "more screen deployment_overview failed type=%s message=%s",
                        type(exc).__name__,
                        exc,
                    )
            management_row = (
                ((Button("⚙️ مدیریت", self._cb("manage")),),) if can_manage else ()
            )

            # deployment.manage is platform-scoped and not inherited from workspace
            # roles, so a platform-only operator correctly has no workspace — the
            # management entry point must not depend on having one.
            _, selected = self._selected(subject)
            if not selected:
                rows = (
                    ((Button("🏫 انتخاب فضای آموزشی", self._cb("workspaces")),),)
                    + management_row
                    + self._nav_rows()
                )
                if can_manage:
                    return ActionResult(
                        semantic_screen(
                            "🏫 فضای آموزشی",
                            "management_without_workspace",
                            severity="info",
                            intro="برای استفاده از بخش‌های فضای آموزشی، یک فضای آموزشی فعال انتخاب کنید.",
                            sections=(
                                SemanticSection(
                                    title="⚙️ مدیریت",
                                    body="مدیریت به فضای آموزشی نیاز ندارد و از همین‌جا در دسترس است.",
                                ),
                            ),
                            rows=rows,
                        )
                    )
                return ActionResult(
                    warning_screen(
                        "ابتدا یک فضای آموزشی فعال انتخاب کنید.",
                        title="🏫 فضای آموزشی",
                        kind="workspace_required",
                        rows=rows,
                    )
                )

            representative_row: tuple[tuple[Button, ...], ...] = ()
            try:
                self.backend.representative_requests_list(self.platform, subject, selected)
                representative_row = ((Button("📋 درخواست‌های عضویت", self._cb("reprequests")),),)
            except FanoosApiError as exc:
                if exc.code not in ("forbidden", "workspace_forbidden"):
                    logging.warning(
                        "more screen representative_requests_list failed code=%s",
                        exc.code,
                    )
            except Exception as exc:
                logging.warning(
                    "more screen representative_requests_list failed type=%s message=%s",
                    type(exc).__name__,
                    exc,
                )

            class_terms_row: tuple[tuple[Button, ...], ...] = ()
            try:
                terms_page = self.backend.academic_terms_list(self.platform, subject, selected)
                if terms_page.get("can_override") is True:
                    class_terms_row = ((Button("📅 ترم‌های کلاس", self._cb("acterms")),),)
            except FanoosApiError as exc:
                if exc.code not in ("forbidden", "workspace_forbidden"):
                    logging.warning(
                        "more screen academic_terms_list failed code=%s",
                        exc.code,
                    )
            except Exception as exc:
                logging.warning(
                    "more screen academic_terms_list failed type=%s message=%s",
                    type(exc).__name__,
                    exc,
                )

            rows: tuple[tuple[Button, ...], ...] = (
                (Button("🎓 نمرات", self._cb("grades")), Button("📚 منابع", self._cb("resources"))),
                (Button("📝 آزمون‌ها", self._cb("assess")), Button("💳 خرید و دسترسی", self._cb("payments"))),
                (Button("🏫 فضای آموزشی", self._cb("workspaces")), Button("👤 حساب", self._cb("account"))),
            )
            rows += representative_row
            rows += class_terms_row
            rows += management_row
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
            return self._error(exc, subject=subject, gated_label="برنامه درس")

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
            return self._error(exc, subject=subject, gated_label="نمرات درس")

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
            return self._error(exc, subject=subject, gated_label="برنامه کلاسی")

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
            return self._error(exc, subject=subject, gated_label="برنامه کلاسی")

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
            return self._error(exc, subject=subject, gated_label="نمرات")

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
            rows = (
                (Button("🔄 به‌روزرسانی سرور", self._cb("update")),),
                (Button("➕ ساخت کلاس", self._cb("clsnew")),),
                (Button("➕ انتصاب نماینده", self._cb("repnew")),),
                (Button("📋 درخواست‌های ساخت کلاس", self._cb("cqlist")),),
                (Button("📅 تنظیم ترم‌ها", self._cb("trmlist")),),
            )
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

    def _class_wizard_steps(self) -> tuple[str, ...]:
        if self.config.default_country_code and self.config.default_country_name:
            return tuple(key for key in _CLASS_STEP_ORDER if key not in ("country_name", "country_code"))
        return _CLASS_STEP_ORDER

    def _class_wizard_step_screen(
        self,
        steps: tuple[str, ...],
        index: int,
        answers: dict,
        *,
        error: str | None = None,
    ):
        key = steps[index]
        label, prompt = _CLASS_STEP_PROMPTS[key]
        counter = f"مرحله {to_persian_digits(index + 1)} از {to_persian_digits(len(steps) + 1)}"
        nav: list[Button] = []
        if index > 0:
            nav.append(Button("↩️ مرحله قبل", self._cb("clsback")))
        nav.append(Button("❌ لغو", self._cb("clscancel")))
        facts: list[tuple[str, str]] = []
        existing = answers.get(key)
        if existing:
            value = to_persian_digits(existing) if key == "entry_year" else str(existing)
            facts.append(("مقدار قبلی", value))
        return semantic_screen(
            f"➕ ساخت کلاس · {label}",
            "class_wizard_step",
            severity="warning" if error else "info",
            breadcrumb="بیشتر › مدیریت › ساخت کلاس",
            intro=(f"⚠️ {error}\n\n{prompt}" if error else prompt),
            facts=facts,
            pagination=counter,
            rows=(tuple(nav),),
        )

    def _class_wizard_review_screen(self, steps: tuple[str, ...], answers: dict):
        facts: list[tuple[str, str]] = []
        if self.config.default_country_code and self.config.default_country_name:
            facts.append(("کشور", self.config.default_country_name))
        for key in steps:
            label, _ = _CLASS_STEP_PROMPTS[key]
            value = answers.get(key, "")
            facts.append((label, to_persian_digits(value) if key == "entry_year" else str(value)))
        rows = (
            (Button("✅ ساخت کلاس", self._cb("clsconfirm")),),
            (Button("↩️ مرحله قبل", self._cb("clsback")), Button("❌ لغو", self._cb("clscancel"))),
        )
        return semantic_screen(
            "➕ ساخت کلاس · بازبینی",
            "class_wizard_review",
            breadcrumb="بیشتر › مدیریت › ساخت کلاس › بازبینی",
            intro="پیش از ساخت کلاس، اطلاعات را بررسی کنید.",
            facts=facts,
            rows=rows,
        )

    def _class_wizard_expired(self):
        return ActionResult(
            warning_screen(
                "این فرآیند ساخت کلاس منقضی شده یا برای این حساب نیست. دوباره از بخش مدیریت شروع کنید.",
                title="➕ ساخت کلاس",
                kind="class_wizard_expired",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def _class_wizard_identity(self, answers: dict) -> dict:
        country_code = answers.get("country_code") or self.config.default_country_code
        country_name = answers.get("country_name") or self.config.default_country_name
        entry_year = int(from_persian_digits(str(answers.get("entry_year") or "0")) or 0)
        return {
            "country": {"code": country_code, "name": country_name},
            "province": {"name": answers.get("province", "")},
            "city": {"name": answers.get("city", "")},
            "institution": {"name": answers.get("institution", "")},
            "faculty": {"name": answers.get("faculty", "")},
            "department": None,
            "program": {
                "name": answers.get("program", ""),
                "degree_level": answers.get("degree_level", ""),
            },
            "cohort": {"entry_year": entry_year, "label": f"ورودی {to_persian_digits(entry_year)}"},
            "workspace": {"name": answers.get("class_name", "")},
        }

    def class_wizard_begin(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return ActionResult(
                warning_screen(
                    "ساخت کلاس فقط در گفت‌وگوی خصوصی تلگرام و پس از مجوز canonical فعال است.",
                    title="➕ ساخت کلاس",
                    kind="class_wizard_unavailable",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        if not self.config.deployment_target_key:
            return ActionResult(
                warning_screen(
                    "بررسی مجوز ساخت کلاس برای این محیط تنظیم نشده است.",
                    title="➕ ساخت کلاس",
                    kind="class_wizard_unavailable",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        try:
            # workspace.provision has no cheap standalone read yet; every owner
            # action on this screen already independently re-verifies through
            # deployment_overview rather than trusting screen-reachability
            # (see update_begin). The canonical authority is still the
            # backend's own workspace.provision check on the create call in
            # class_wizard_confirm; this call only decides what to show.
            overview = self.backend.deployment_overview(subject, self.config.deployment_target_key)
        except Exception as exc:
            logging.warning(
                "class wizard begin deployment_overview failed type=%s message=%s",
                type(exc).__name__,
                exc,
            )
            return self._error(exc)
        if not overview.get("can_manage_deployments"):
            return ActionResult(
                error_screen(
                    "اجازه ساخت کلاس را ندارید.",
                    title="➕ ساخت کلاس",
                    kind="class_wizard_denied",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        self.state.start_class_wizard(self.platform, subject)
        steps = self._class_wizard_steps()
        return ActionResult(self._class_wizard_step_screen(steps, 0, {}))

    def class_wizard_text(self, subject: str, text: str, private: bool = True):
        """Advance an in-progress class wizard with free text, or return None.

        None means "no active wizard for this subject": the caller (bot
        runtime) should fall through to normal command dispatch instead of
        treating the message as a wizard answer.
        """
        if not private:
            return None
        wizard = self.state.class_wizard(self.platform, subject)
        if wizard is None:
            return None
        steps = self._class_wizard_steps()
        index = wizard["step_index"]
        answers = wizard["answers"]
        if index >= len(steps):
            return ActionResult(self._class_wizard_review_screen(steps, answers))
        key = steps[index]
        value, error = _class_field_validator(key)(text)
        if error:
            return ActionResult(self._class_wizard_step_screen(steps, index, answers, error=error))
        next_answers = dict(answers)
        next_answers[key] = value
        next_index = index + 1
        self.state.advance_class_wizard(self.platform, subject, next_index, next_answers)
        if next_index >= len(steps):
            return ActionResult(self._class_wizard_review_screen(steps, next_answers))
        return ActionResult(self._class_wizard_step_screen(steps, next_index, next_answers))

    def class_wizard_back(self, subject: str):
        wizard = self.state.class_wizard(self.platform, subject)
        if wizard is None:
            return self._class_wizard_expired()
        steps = self._class_wizard_steps()
        index = max(0, wizard["step_index"] - 1)
        self.state.advance_class_wizard(self.platform, subject, index, wizard["answers"])
        return ActionResult(self._class_wizard_step_screen(steps, index, wizard["answers"]))

    def class_wizard_cancel(self, subject: str):
        self.state.cancel_class_wizard(self.platform, subject)
        return ActionResult(
            semantic_screen(
                "➕ ساخت کلاس",
                "class_wizard_cancelled",
                intro="ساخت کلاس لغو شد. اطلاعات واردشده ذخیره نشد.",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def class_wizard_confirm(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return self._class_wizard_expired()
        wizard = self.state.class_wizard(self.platform, subject)
        if wizard is None:
            return self._class_wizard_expired()
        steps = self._class_wizard_steps()
        if wizard["step_index"] < len(steps):
            return ActionResult(
                self._class_wizard_step_screen(steps, wizard["step_index"], wizard["answers"])
            )
        identity = self._class_wizard_identity(wizard["answers"])
        try:
            result = self.backend.create_class(self.platform, subject, identity)
        except Exception as exc:
            # A mid-wizard backend failure must reach the owner, not be
            # swallowed: the wizard state is kept so a retry does not force
            # retyping everything.
            logging.error(
                "class wizard create_class failed type=%s message=%s",
                type(exc).__name__,
                exc,
            )
            message = (
                error_message(exc.code, exc.status)
                if isinstance(exc, FanoosApiError)
                else "ساخت کلاس ناموفق بود. دوباره امتحان کنید."
            )
            return ActionResult(
                error_screen(
                    message,
                    title="➕ ساخت کلاس",
                    kind="class_wizard_failed",
                    rows=(
                        (Button("🔁 تلاش دوباره", self._cb("clsconfirm")),),
                    )
                    + self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        self.state.cancel_class_wizard(self.platform, subject)
        workspace_created = result.get("workspace_created") is True
        facts = [
            ("نام کلاس", str(result.get("workspace_slug") or wizard["answers"].get("class_name") or "")),
            ("وضعیت", "ساخته شد" if workspace_created else "کلاس از قبل وجود داشت و استفاده شد"),
        ]
        return ActionResult(
            semantic_screen(
                "✅ کلاس آماده است",
                "class_wizard_created",
                severity="success",
                breadcrumb="بیشتر › مدیریت › ساخت کلاس",
                intro=(
                    "کلاس جدید ساخته شد."
                    if workspace_created
                    else "این هویت با کلاس موجود مطابقت داشت؛ کلاس تکراری ساخته نشد و همان استفاده شد."
                ),
                facts=facts,
                footer=(
                    "استان، دانشگاه، دانشکده و رشته در صورت نبودن ساخته و در غیر این صورت از ردیف موجود "
                    "استفاده شدند."
                ),
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    # -- Representative appointment wizard (owner) ---------------------------
    # Same shape as the class wizard above: gated by deployment_overview,
    # state in its own small table, review-free here only because there is
    # nothing to mistype -- both choices are made by tapping an inline
    # button, never typed. Two list-picker steps (which class, then which of
    # its members) instead of class_wizard's free-text fields, because the
    # owner is choosing among existing rows, not naming new ones; the person
    # being appointed must already be a member -- this reuses that identity
    # path rather than inventing a second way to name someone
    # (docs/product/01_FRONT_DOOR.md #10).

    APPOINT_PAGE_SIZE = 8

    def _appoint_wizard_cancelled_result(self):
        return ActionResult(
            semantic_screen(
                "➕ انتصاب نماینده",
                "appoint_wizard_cancelled",
                intro="انتصاب نماینده لغو شد.",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def appoint_wizard_begin(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return ActionResult(
                warning_screen(
                    "انتصاب نماینده فقط در گفت‌وگوی خصوصی تلگرام و پس از مجوز canonical فعال است.",
                    title="➕ انتصاب نماینده",
                    kind="appoint_wizard_unavailable",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        if not self.config.deployment_target_key:
            return ActionResult(
                warning_screen(
                    "بررسی مجوز انتصاب نماینده برای این محیط تنظیم نشده است.",
                    title="➕ انتصاب نماینده",
                    kind="appoint_wizard_unavailable",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        try:
            # Same re-verification note as class_wizard_begin: this call only
            # decides what to show, the canonical authority is still the
            # backend's own membership.manage check on the appoint call.
            overview = self.backend.deployment_overview(subject, self.config.deployment_target_key)
        except Exception as exc:
            logging.warning(
                "appoint wizard begin deployment_overview failed type=%s message=%s",
                type(exc).__name__,
                exc,
            )
            return self._error(exc)
        if not overview.get("can_manage_deployments"):
            return ActionResult(
                error_screen(
                    "اجازه انتصاب نماینده را ندارید.",
                    title="➕ انتصاب نماینده",
                    kind="appoint_wizard_denied",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        self.state.start_appoint_wizard(self.platform, subject)
        return self._appoint_wizard_render_workspaces(subject, None)

    def appoint_wizard_cancel(self, subject: str):
        self.state.cancel_appoint_wizard(self.platform, subject)
        return self._appoint_wizard_cancelled_result()

    def appoint_wizard_back(self, subject: str):
        wizard = self.state.appoint_wizard(self.platform, subject)
        if wizard is None:
            return self._appoint_wizard_expired()
        if wizard["step_index"] <= 0:
            self.state.cancel_appoint_wizard(self.platform, subject)
            return self._appoint_wizard_cancelled_result()
        if wizard["step_index"] == 1:
            self.state.advance_appoint_wizard(self.platform, subject, 0, {})
            return self._appoint_wizard_render_workspaces(subject, None)
        answers = dict(wizard["answers"])
        answers.pop("target_user_id", None)
        answers.pop("target_name", None)
        self.state.advance_appoint_wizard(self.platform, subject, 1, answers)
        return self._appoint_wizard_render_candidates(subject, answers, None)

    def _appoint_wizard_expired(self):
        return ActionResult(
            warning_screen(
                "این فرآیند دیگر در دسترس نیست. از مدیریت دوباره شروع کنید.",
                title="➕ انتصاب نماینده",
                kind="appoint_wizard_expired",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def _appoint_wizard_render_workspaces(self, subject: str, cursor: str | None):
        try:
            page = self.backend.representative_workspaces(self.platform, subject, self.APPOINT_PAGE_SIZE, cursor)
        except Exception as exc:
            return self._error(exc)
        items = [item for item in page.get("items") or [] if is_uuid(str(item.get("id") or ""))]
        next_cursor = self._clean_cursor(page.get("next_cursor"))
        rows: tuple[tuple[Button, ...], ...] = tuple(
            (Button(
                self._workspace_label(item),
                self._route_callback(subject, "appoint_workspace_pick", "apws", {
                    "id": str(item.get("id") or ""), "name": self._workspace_label(item),
                }),
            ),)
            for item in items
        )
        if next_cursor:
            rows += ((Button(
                "بعدی ›",
                self._route_callback(subject, "appoint_workspace_page", "apwsp", {"cursor": next_cursor}),
            ),),)
        rows += self._nav_rows(back_action="manage", back_label="‹ مدیریت")
        return ActionResult(
            semantic_screen(
                "➕ انتصاب نماینده",
                "appoint_wizard_workspace",
                breadcrumb="بیشتر › مدیریت › انتصاب نماینده",
                intro=(
                    "کلاسی که می‌خواهید برایش نماینده انتصاب کنید را انتخاب کنید."
                    if items
                    else "کلاس فعالی برای انتصاب نماینده وجود ندارد."
                ),
                rows=rows,
            )
        )

    def appoint_wizard_pick_workspace(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "appoint_workspace_pick")
        if not payload or not is_uuid(str(payload.get("id") or "")):
            return self._expired_route()
        wizard = self.state.appoint_wizard(self.platform, subject)
        if wizard is None:
            return self._appoint_wizard_expired()
        answers = {"workspace_id": str(payload["id"]), "workspace_name": str(payload.get("name") or "")}
        self.state.advance_appoint_wizard(self.platform, subject, 1, answers)
        return self._appoint_wizard_render_candidates(subject, answers, None)

    def appoint_wizard_page_workspaces(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "appoint_workspace_page")
        if not payload:
            return self._expired_route()
        return self._appoint_wizard_render_workspaces(subject, self._clean_cursor(payload.get("cursor")))

    def _appoint_wizard_render_candidates(self, subject: str, answers: dict, cursor: str | None):
        workspace_id = str(answers.get("workspace_id") or "")
        if not is_uuid(workspace_id):
            return self._appoint_wizard_expired()
        try:
            page = self.backend.representative_candidates(self.platform, subject, workspace_id, self.APPOINT_PAGE_SIZE, cursor)
        except Exception as exc:
            return self._error(exc)
        items = [item for item in page.get("items") or [] if is_uuid(str(item.get("user_id") or ""))]
        next_cursor = self._clean_cursor(page.get("next_cursor"))

        def label(item: dict) -> str:
            return truncate_text(str(item.get("display_name") or "عضو کلاس"), 70)

        rows: tuple[tuple[Button, ...], ...] = tuple(
            (Button(
                label(item),
                self._route_callback(subject, "appoint_candidate_pick", "apcd", {
                    "id": str(item.get("user_id") or ""), "name": label(item),
                }),
            ),)
            for item in items
        )
        if next_cursor:
            rows += ((Button(
                "بعدی ›",
                self._route_callback(subject, "appoint_candidate_page", "apcdp", {
                    "cursor": next_cursor, "workspace_id": workspace_id, "workspace_name": answers.get("workspace_name") or "",
                }),
            ),),)
        rows += (
            (Button("↩️ بازگشت", self._cb("apback")), Button("انصراف", self._cb("apcancel"))),
        ) + self._nav_rows(back_action="manage", back_label="‹ مدیریت")
        workspace_name = str(answers.get("workspace_name") or "")
        return ActionResult(
            semantic_screen(
                "➕ انتصاب نماینده",
                "appoint_wizard_candidate",
                breadcrumb="بیشتر › مدیریت › انتصاب نماینده",
                intro=(
                    f"عضوی از «{workspace_name}» را برای نمایندگی انتخاب کنید."
                    if items
                    else f"«{workspace_name}» عضو فعالی ندارد."
                ),
                rows=rows,
            )
        )

    def appoint_wizard_pick_candidate(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "appoint_candidate_pick")
        if not payload or not is_uuid(str(payload.get("id") or "")):
            return self._expired_route()
        wizard = self.state.appoint_wizard(self.platform, subject)
        if wizard is None:
            return self._appoint_wizard_expired()
        answers = dict(wizard["answers"])
        answers["target_user_id"] = str(payload["id"])
        answers["target_name"] = str(payload.get("name") or "")
        self.state.advance_appoint_wizard(self.platform, subject, 2, answers)
        return self._appoint_wizard_render_confirm(answers)

    def appoint_wizard_page_candidates(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "appoint_candidate_page")
        if not payload:
            return self._expired_route()
        answers = {
            "workspace_id": str(payload.get("workspace_id") or ""),
            "workspace_name": str(payload.get("workspace_name") or ""),
        }
        return self._appoint_wizard_render_candidates(subject, answers, self._clean_cursor(payload.get("cursor")))

    def _appoint_wizard_render_confirm(self, answers: dict):
        workspace_name = str(answers.get("workspace_name") or "")
        target_name = str(answers.get("target_name") or "")
        rows = (
            (Button("✅ انتصاب شود", self._cb("apconfirm")),),
            (Button("↩️ بازگشت", self._cb("apback")), Button("انصراف", self._cb("apcancel"))),
        )
        return ActionResult(
            semantic_screen(
                "➕ انتصاب نماینده",
                "appoint_wizard_confirm",
                breadcrumb="بیشتر › مدیریت › انتصاب نماینده",
                intro=f"«{target_name}» به‌عنوان نماینده «{workspace_name}» منصوب شود؟",
                rows=rows,
            )
        )

    def appoint_wizard_confirm(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return self._appoint_wizard_expired()
        wizard = self.state.appoint_wizard(self.platform, subject)
        if wizard is None:
            return self._appoint_wizard_expired()
        answers = wizard["answers"]
        workspace_id = str(answers.get("workspace_id") or "")
        target_user_id = str(answers.get("target_user_id") or "")
        if not is_uuid(workspace_id) or not is_uuid(target_user_id):
            return self._appoint_wizard_expired()
        try:
            self.backend.representative_appoint(self.platform, subject, workspace_id, target_user_id)
        except Exception as exc:
            logging.error(
                "appoint wizard representative_appoint failed type=%s message=%s",
                type(exc).__name__,
                exc,
            )
            message = (
                error_message(exc.code, exc.status)
                if isinstance(exc, FanoosApiError)
                else "انتصاب نماینده ناموفق بود. دوباره امتحان کنید."
            )
            return ActionResult(
                error_screen(
                    message,
                    title="➕ انتصاب نماینده",
                    kind="appoint_wizard_failed",
                    rows=(
                        (Button("🔁 تلاش دوباره", self._cb("apconfirm")),),
                    )
                    + self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        self.state.cancel_appoint_wizard(self.platform, subject)
        return ActionResult(
            semantic_screen(
                "✅ نماینده منصوب شد",
                "appoint_wizard_created",
                severity="success",
                breadcrumb="بیشتر › مدیریت › انتصاب نماینده",
                intro=f"«{answers.get('target_name') or ''}» نماینده «{answers.get('workspace_name') or ''}» شد.",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    # -- Class-creation request review (owner) -------------------------------
    # Capability 5's consumption side (docs/product/01_FRONT_DOOR.md #9):
    # ClassMembershipService::requestClassCreation() already records a
    # student's request when their class does not exist yet; nothing read
    # those rows until this. Grouped by (program_id, entry_year), since
    # several students land on the same identity -- the demand count is the
    # number waiting, and is the entire point of this screen. Gated the same
    # way as class_wizard_begin/appoint_wizard_begin: workspace.provision has
    # no cheap standalone read yet, so deployment_overview's
    # can_manage_deployments decides what to show here, while every actual
    # list/approve/decline call is independently re-authorized by the
    # backend's own workspace.provision check.

    CREQ_PAGE_SIZE = 8

    _CREQ_STEP_PROMPTS: dict[str, tuple[str, str]] = {
        "cohort_label": ("برچسب ورودی", "یک برچسب برای این ورودی بنویسید؛ مثلاً «ورودی ۱۴۰۲»."),
        "workspace_name": (
            "نام نمایشی کلاس",
            "یک نام نمایشی برای این کلاس بنویسید؛ همین نام برای اعضا نمایش داده می‌شود.",
        ),
    }
    _CREQ_STEP_ORDER: tuple[str, ...] = ("cohort_label", "workspace_name")
    _CREQ_TEXT_LIMITS: dict[str, tuple[int, int]] = {
        "cohort_label": (1, 160),
        "workspace_name": (1, 200),
    }

    @staticmethod
    def _creq_group_label(group: dict) -> str:
        parts = [str(group.get(key) or "") for key in ("institution", "faculty", "program")]
        label = " · ".join(part for part in parts if part)
        year = group.get("entry_year")
        if year:
            label = f"{label} · ورودی {to_persian_digits(year)}" if label else f"ورودی {to_persian_digits(year)}"
        return truncate_text(label or "کلاس", 90)

    def class_creation_requests_begin(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return ActionResult(
                warning_screen(
                    "درخواست‌های ساخت کلاس فقط در گفت‌وگوی خصوصی تلگرام و پس از مجوز canonical فعال است.",
                    title="📋 درخواست‌های ساخت کلاس",
                    kind="creq_list_unavailable",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        if not self.config.deployment_target_key:
            return ActionResult(
                warning_screen(
                    "بررسی مجوز این بخش برای این محیط تنظیم نشده است.",
                    title="📋 درخواست‌های ساخت کلاس",
                    kind="creq_list_unavailable",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        try:
            overview = self.backend.deployment_overview(subject, self.config.deployment_target_key)
        except Exception as exc:
            return self._error(exc)
        if not overview.get("can_manage_deployments"):
            return ActionResult(
                error_screen(
                    "اجازه دیدن درخواست‌های ساخت کلاس را ندارید.",
                    title="📋 درخواست‌های ساخت کلاس",
                    kind="creq_list_denied",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        return self._creq_render_list(subject, None)

    def _creq_render_list(self, subject: str, cursor: str | None):
        try:
            page = self.backend.class_creation_requests_list(self.platform, subject, self.CREQ_PAGE_SIZE, cursor)
        except Exception as exc:
            return self._error(exc)
        items = [
            item for item in page.get("items") or []
            if isinstance(item, dict) and is_uuid(str(item.get("program_id") or ""))
        ]
        next_cursor = self._clean_cursor(page.get("next_cursor"))

        def row_label(group: dict) -> str:
            count = to_persian_digits(group.get("demand_count") or 0)
            return truncate_text(f"{self._creq_group_label(group)} ({count} نفر)", 90)

        rows: tuple[tuple[Button, ...], ...] = tuple(
            (Button(
                row_label(group),
                self._route_callback(subject, "creq_pick", "cqpick", group),
            ),)
            for item in items
            for group in (
                {
                    "program_id": str(item.get("program_id") or ""),
                    "entry_year": int(item.get("entry_year") or 0),
                    "institution": str(item.get("institution_name") or ""),
                    "faculty": str(item.get("faculty_name") or ""),
                    "program": str(item.get("program_name") or ""),
                    "demand_count": int(item.get("demand_count") or 0),
                },
            )
        )
        if next_cursor:
            rows += ((Button(
                "بعدی ›",
                self._route_callback(subject, "creq_page", "cqpage", {"cursor": next_cursor}),
            ),),)
        rows += self._nav_rows(back_action="manage", back_label="‹ مدیریت")
        return ActionResult(
            semantic_screen(
                "📋 درخواست‌های ساخت کلاس",
                "creq_list",
                breadcrumb="بیشتر › مدیریت › درخواست‌های ساخت کلاس",
                intro=(
                    "کلاس‌هایی که دانشجویان برای ساختشان درخواست داده‌اند؛ عدد جلوی هرکدام تعداد افرادی است "
                    "که منتظرند."
                    if items
                    else "در حال حاضر درخواست ساخت کلاسی در انتظار بررسی نیست."
                ),
                rows=rows,
            )
        )

    def class_creation_requests_page(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "creq_page")
        if not payload:
            return self._expired_route()
        return self._creq_render_list(subject, self._clean_cursor(payload.get("cursor")))

    @staticmethod
    def _creq_group_from_payload(payload: dict) -> dict:
        return {
            "program_id": str(payload.get("program_id") or ""),
            "entry_year": int(payload.get("entry_year") or 0),
            "institution": str(payload.get("institution") or ""),
            "faculty": str(payload.get("faculty") or ""),
            "program": str(payload.get("program") or ""),
            "demand_count": int(payload.get("demand_count") or 0),
        }

    def _creq_detail_screen(self, subject: str, group: dict):
        rows = (
            (Button("✅ تأیید و ساخت کلاس", self._route_callback(subject, "creq_approve", "cqappr", group)),),
            (Button("❌ رد درخواست‌ها", self._route_callback(subject, "creq_decline", "cqdecl", group)),),
            (Button("↩️ بازگشت", self._cb("cqlist")),),
        ) + self._nav_rows(back_action="manage", back_label="‹ مدیریت")
        count = to_persian_digits(group.get("demand_count") or 0)
        return ActionResult(
            semantic_screen(
                "📋 درخواست ساخت کلاس",
                "creq_detail",
                breadcrumb="بیشتر › مدیریت › درخواست‌های ساخت کلاس",
                intro=f"«{self._creq_group_label(group)}» — {count} نفر منتظر این کلاس هستند.",
                rows=rows,
            )
        )

    def class_creation_request_pick(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "creq_pick")
        if not payload or not is_uuid(str(payload.get("program_id") or "")):
            return self._expired_route()
        return self._creq_detail_screen(subject, self._creq_group_from_payload(payload))

    def class_creation_request_approve_begin(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "creq_approve")
        if not payload or not is_uuid(str(payload.get("program_id") or "")):
            return self._expired_route()
        group = self._creq_group_from_payload(payload)
        self.state.start_creq_wizard(self.platform, subject, group)
        return ActionResult(self._creq_wizard_step_screen(self._CREQ_STEP_ORDER, 0, group))

    def _creq_wizard_step_screen(
        self,
        steps: tuple[str, ...],
        index: int,
        answers: dict,
        *,
        error: str | None = None,
    ):
        key = steps[index]
        label, prompt = self._CREQ_STEP_PROMPTS[key]
        counter = f"مرحله {to_persian_digits(index + 1)} از {to_persian_digits(len(steps) + 1)}"
        nav: list[Button] = []
        if index > 0:
            nav.append(Button("↩️ مرحله قبل", self._cb("cqaback")))
        nav.append(Button("❌ لغو", self._cb("cqacxl")))
        body = f"«{self._creq_group_label(answers)}»\n\n{prompt}"
        return semantic_screen(
            f"➕ ساخت کلاس · {label}",
            "creq_wizard_step",
            severity="warning" if error else "info",
            breadcrumb="بیشتر › مدیریت › درخواست‌های ساخت کلاس › ساخت",
            intro=(f"⚠️ {error}\n\n{prompt}" if error else body),
            pagination=counter,
            rows=(tuple(nav),),
        )

    def _creq_wizard_review_screen(self, answers: dict):
        facts = [
            ("کلاس", self._creq_group_label(answers)),
            ("برچسب ورودی", str(answers.get("cohort_label") or "")),
            ("نام نمایشی کلاس", str(answers.get("workspace_name") or "")),
        ]
        rows = (
            (Button("✅ ساخت کلاس", self._cb("cqaconf")),),
            (Button("↩️ مرحله قبل", self._cb("cqaback")), Button("❌ لغو", self._cb("cqacxl"))),
        )
        return semantic_screen(
            "➕ ساخت کلاس · بازبینی",
            "creq_wizard_review",
            breadcrumb="بیشتر › مدیریت › درخواست‌های ساخت کلاس › بازبینی",
            intro="پیش از ساخت کلاس و بستن درخواست‌ها، اطلاعات را بررسی کنید.",
            facts=facts,
            rows=rows,
        )

    def _creq_wizard_expired(self):
        return ActionResult(
            warning_screen(
                "این فرآیند دیگر در دسترس نیست. از «درخواست‌های ساخت کلاس» دوباره شروع کنید.",
                title="➕ ساخت کلاس",
                kind="creq_wizard_expired",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def class_creation_request_wizard_text(self, subject: str, text: str, private: bool = True):
        """Advance an in-progress class-creation-request approve wizard with
        free text, or return None so the runtime falls through to other
        handlers -- same contract as class_wizard_text."""
        if not private:
            return None
        wizard = self.state.creq_wizard(self.platform, subject)
        if wizard is None:
            return None
        steps = self._CREQ_STEP_ORDER
        index = wizard["step_index"]
        answers = wizard["answers"]
        if index >= len(steps):
            return ActionResult(self._creq_wizard_review_screen(answers))
        key = steps[index]
        min_len, max_len = self._CREQ_TEXT_LIMITS[key]
        value, error = _validate_class_text(text, min_len, max_len)
        if error:
            return ActionResult(self._creq_wizard_step_screen(steps, index, answers, error=error))
        next_answers = dict(answers)
        next_answers[key] = value
        next_index = index + 1
        self.state.advance_creq_wizard(self.platform, subject, next_index, next_answers)
        if next_index >= len(steps):
            return ActionResult(self._creq_wizard_review_screen(next_answers))
        return ActionResult(self._creq_wizard_step_screen(steps, next_index, next_answers))

    def class_creation_request_wizard_back(self, subject: str):
        wizard = self.state.creq_wizard(self.platform, subject)
        if wizard is None:
            return self._creq_wizard_expired()
        steps = self._CREQ_STEP_ORDER
        index = max(0, wizard["step_index"] - 1)
        self.state.advance_creq_wizard(self.platform, subject, index, wizard["answers"])
        return ActionResult(self._creq_wizard_step_screen(steps, index, wizard["answers"]))

    def class_creation_request_wizard_cancel(self, subject: str):
        self.state.cancel_creq_wizard(self.platform, subject)
        return ActionResult(
            semantic_screen(
                "➕ ساخت کلاس",
                "creq_wizard_cancelled",
                intro="ساخت کلاس لغو شد. درخواست‌ها همچنان در انتظار بررسی‌اند.",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def class_creation_request_wizard_confirm(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return self._creq_wizard_expired()
        wizard = self.state.creq_wizard(self.platform, subject)
        if wizard is None:
            return self._creq_wizard_expired()
        answers = wizard["answers"]
        steps = self._CREQ_STEP_ORDER
        if wizard["step_index"] < len(steps):
            return ActionResult(self._creq_wizard_step_screen(steps, wizard["step_index"], answers))
        program_id = str(answers.get("program_id") or "")
        entry_year = int(answers.get("entry_year") or 0)
        if not is_uuid(program_id):
            return self._creq_wizard_expired()
        try:
            result = self.backend.class_creation_requests_approve(
                self.platform, subject, program_id, entry_year,
                str(answers.get("cohort_label") or ""), str(answers.get("workspace_name") or ""),
            )
        except Exception as exc:
            # A mid-wizard backend failure must reach the owner, not be
            # swallowed -- same discipline as class_wizard_confirm above.
            logging.error(
                "class creation request approve failed type=%s message=%s",
                type(exc).__name__,
                exc,
            )
            message = (
                error_message(exc.code, exc.status)
                if isinstance(exc, FanoosApiError)
                else "ساخت کلاس ناموفق بود. دوباره امتحان کنید."
            )
            return ActionResult(
                error_screen(
                    message,
                    title="➕ ساخت کلاس",
                    kind="creq_wizard_failed",
                    rows=(
                        (Button("🔁 تلاش دوباره", self._cb("cqaconf")),),
                    )
                    + self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        self.state.cancel_creq_wizard(self.platform, subject)
        workspace_created = result.get("workspace_created") is True
        resolved_count = to_persian_digits(result.get("resolved_count") or 0)
        return ActionResult(
            semantic_screen(
                "✅ کلاس آماده است",
                "creq_wizard_created",
                severity="success",
                breadcrumb="بیشتر › مدیریت › درخواست‌های ساخت کلاس",
                intro=(
                    "کلاس جدید ساخته شد."
                    if workspace_created
                    else "این هویت با کلاس موجود مطابقت داشت؛ کلاس تکراری ساخته نشد و همان استفاده شد."
                ),
                facts=[("درخواست‌های بسته‌شده", resolved_count)],
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def class_creation_request_decline_confirm(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "creq_decline")
        if not payload or not is_uuid(str(payload.get("program_id") or "")):
            return self._expired_route()
        group = self._creq_group_from_payload(payload)
        rows = (
            (Button("❌ بله، رد شوند", self._route_callback(subject, "creq_decline_do", "cqdyes", group)),),
            (Button("↩️ بازگشت", self._route_callback(subject, "creq_pick", "cqdno", group)),),
        )
        count = to_persian_digits(group.get("demand_count") or 0)
        return ActionResult(
            semantic_screen(
                "❌ رد درخواست‌های ساخت کلاس",
                "creq_decline_confirm",
                severity="warning",
                breadcrumb="بیشتر › مدیریت › درخواست‌های ساخت کلاس",
                intro=f"همه‌ی {count} درخواست برای «{self._creq_group_label(group)}» رد شود؟ این کار قابل بازگشت نیست.",
                rows=rows,
            )
        )

    def class_creation_request_decline_do(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "creq_decline_do")
        if not payload or not is_uuid(str(payload.get("program_id") or "")):
            return self._expired_route()
        program_id = str(payload.get("program_id") or "")
        entry_year = int(payload.get("entry_year") or 0)
        try:
            result = self.backend.class_creation_requests_decline(self.platform, subject, program_id, entry_year)
        except Exception as exc:
            logging.error(
                "class creation request decline failed type=%s message=%s",
                type(exc).__name__,
                exc,
            )
            return self._error(exc)
        count = to_persian_digits(result.get("declined_count") or 0)
        return ActionResult(
            semantic_screen(
                "❌ درخواست‌ها رد شد",
                "creq_declined",
                severity="success",
                breadcrumb="بیشتر › مدیریت › درخواست‌های ساخت کلاس",
                intro=f"{count} درخواست رد شد.",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    # -- Institution term dates (owner) --------------------------------------
    # docs/product/01_FRONT_DOOR.md #4: term dates are configured once per
    # institution, not re-typed per class. Gated the same way as the other
    # owner-only screens above: deployment_overview's can_manage_deployments
    # decides what to show here, while every actual list/set call is
    # independently re-authorized by the backend's own workspace.provision
    # check. Dates are typed and shown in Jalali (persian_datetime) and sent
    # to the backend as Gregorian ISO dates; storage and every existing read
    # stay untouched. The representative's own class-term override below
    # shares this same small wizard (term_wizards), distinguished by an
    # internal 'mode' marker in its answers, since only one such wizard is
    # ever active per subject at a time.

    TERM_PAGE_SIZE = 8

    _TERM_STEP_PROMPTS: dict[str, tuple[str, str]] = {
        "term_key": ("شناسه ترم", "یک شناسه کوتاه انگلیسی برای این ترم بنویسید؛ مثلاً fall-1406."),
        "name": ("نام ترم", "یک نام نمایشی برای این ترم بنویسید؛ مثلاً «نیم‌سال اول ۱۴۰۶-۱۴۰۷»."),
        "starts_on": ("تاریخ شروع", "تاریخ شروع ترم را به شمسی بنویسید؛ مثلاً ۱۴۰۶/۰۷/۰۱."),
        "ends_on": ("تاریخ پایان", "تاریخ پایان ترم را به شمسی بنویسید؛ مثلاً ۱۴۰۶/۱۰/۱۵."),
    }
    _TERM_STEP_ORDER: tuple[str, ...] = ("term_key", "name", "starts_on", "ends_on")
    _CLASS_TERM_STEP_PROMPTS: dict[str, tuple[str, str]] = {
        "starts_on": ("تاریخ شروع", "تاریخ شروع تازه این ترم را به شمسی بنویسید؛ مثلاً ۱۴۰۶/۰۷/۰۱."),
        "ends_on": ("تاریخ پایان", "تاریخ پایان تازه این ترم را به شمسی بنویسید؛ مثلاً ۱۴۰۶/۱۰/۱۵."),
    }
    _CLASS_TERM_STEP_ORDER: tuple[str, ...] = ("starts_on", "ends_on")

    def _term_wizard_steps(self, answers: dict) -> tuple[str, ...]:
        return self._CLASS_TERM_STEP_ORDER if answers.get("mode") == "class_term" else self._TERM_STEP_ORDER

    def _term_wizard_prompts(self, answers: dict) -> dict[str, tuple[str, str]]:
        return self._CLASS_TERM_STEP_PROMPTS if answers.get("mode") == "class_term" else self._TERM_STEP_PROMPTS

    def institution_terms_begin(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return ActionResult(
                warning_screen(
                    "تنظیم ترم‌ها فقط در گفت‌وگوی خصوصی تلگرام و پس از مجوز canonical فعال است.",
                    title="📅 تنظیم ترم‌ها",
                    kind="term_list_unavailable",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        if not self.config.deployment_target_key:
            return ActionResult(
                warning_screen(
                    "بررسی مجوز این بخش برای این محیط تنظیم نشده است.",
                    title="📅 تنظیم ترم‌ها",
                    kind="term_list_unavailable",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        try:
            overview = self.backend.deployment_overview(subject, self.config.deployment_target_key)
        except Exception as exc:
            return self._error(exc)
        if not overview.get("can_manage_deployments"):
            return ActionResult(
                error_screen(
                    "اجازه تنظیم ترم‌ها را ندارید.",
                    title="📅 تنظیم ترم‌ها",
                    kind="term_list_denied",
                    rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        return self._term_render_institutions(subject, None)

    def _term_render_institutions(self, subject: str, cursor: str | None):
        try:
            page = self.backend.institution_terms_institutions(self.platform, subject, self.TERM_PAGE_SIZE, cursor)
        except Exception as exc:
            return self._error(exc)
        items = [
            item for item in page.get("items") or []
            if isinstance(item, dict) and is_uuid(str(item.get("id") or ""))
        ]
        next_cursor = self._clean_cursor(page.get("next_cursor"))

        def label(item: dict) -> str:
            return truncate_text(str(item.get("name") or "دانشگاه"), 70)

        rows: tuple[tuple[Button, ...], ...] = tuple(
            (Button(
                label(item),
                self._route_callback(subject, "term_institution_pick", "trmpick", {
                    "institution_id": str(item.get("id") or ""), "institution_name": label(item),
                }),
            ),)
            for item in items
        )
        if next_cursor:
            rows += ((Button(
                "بعدی ›",
                self._route_callback(subject, "term_institution_page", "trmpage", {"cursor": next_cursor}),
            ),),)
        rows += self._nav_rows(back_action="manage", back_label="‹ مدیریت")
        return ActionResult(
            semantic_screen(
                "📅 تنظیم ترم‌ها",
                "term_institution_list",
                breadcrumb="بیشتر › مدیریت › تنظیم ترم‌ها",
                intro=(
                    "دانشگاهی که می‌خواهید برایش ترم تنظیم کنید را انتخاب کنید."
                    if items
                    else "دانشگاهی برای تنظیم ترم یافت نشد."
                ),
                rows=rows,
            )
        )

    def institution_terms_page(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "term_institution_page")
        if not payload:
            return self._expired_route()
        return self._term_render_institutions(subject, self._clean_cursor(payload.get("cursor")))

    def institution_term_pick(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "term_institution_pick")
        if not payload or not is_uuid(str(payload.get("institution_id") or "")):
            return self._expired_route()
        answers = {
            "mode": "institution",
            "institution_id": str(payload["institution_id"]),
            "institution_name": str(payload.get("institution_name") or ""),
        }
        self.state.start_term_wizard(self.platform, subject, answers)
        return ActionResult(self._term_wizard_step_screen(self._TERM_STEP_ORDER, 0, answers))

    def _term_wizard_step_screen(
        self,
        steps: tuple[str, ...],
        index: int,
        answers: dict,
        *,
        error: str | None = None,
    ):
        prompts = self._term_wizard_prompts(answers)
        key = steps[index]
        label, prompt = prompts[key]
        counter = f"مرحله {to_persian_digits(index + 1)} از {to_persian_digits(len(steps) + 1)}"
        nav: list[Button] = []
        if index > 0:
            nav.append(Button("↩️ مرحله قبل", self._cb("trmback")))
        nav.append(Button("❌ لغو", self._cb("trmcxl")))
        subtitle = str(answers.get("institution_name") or answers.get("name") or "")
        title_prefix = "📅 تنظیم ترم" if answers.get("mode") != "class_term" else "✏️ ویرایش ترم"
        body = f"«{subtitle}»\n\n{prompt}" if subtitle else prompt
        return semantic_screen(
            f"{title_prefix} · {label}",
            "term_wizard_step" if answers.get("mode") != "class_term" else "class_term_wizard_step",
            severity="warning" if error else "info",
            breadcrumb="بیشتر › مدیریت › تنظیم ترم‌ها" if answers.get("mode") != "class_term" else "بیشتر › ترم‌های کلاس",
            intro=(f"⚠️ {error}\n\n{prompt}" if error else body),
            pagination=counter,
            rows=(tuple(nav),),
        )

    def _term_wizard_review_screen(self, answers: dict):
        if answers.get("mode") == "class_term":
            facts = [
                ("ترم", str(answers.get("name") or answers.get("term_key") or "")),
                ("تاریخ شروع", format_jalali_date(str(answers.get("starts_on") or ""))),
                ("تاریخ پایان", format_jalali_date(str(answers.get("ends_on") or ""))),
            ]
            title = "✏️ ویرایش ترم · بازبینی"
            breadcrumb = "بیشتر › ترم‌های کلاس › بازبینی"
            intro = "پیش از ذخیره، تاریخ‌های تازه را بررسی کنید."
            confirm_label = "✅ ذخیره تاریخ‌ها"
        else:
            facts = [
                ("دانشگاه", str(answers.get("institution_name") or "")),
                ("شناسه ترم", str(answers.get("term_key") or "")),
                ("نام ترم", str(answers.get("name") or "")),
                ("تاریخ شروع", format_jalali_date(str(answers.get("starts_on") or ""))),
                ("تاریخ پایان", format_jalali_date(str(answers.get("ends_on") or ""))),
            ]
            title = "📅 تنظیم ترم · بازبینی"
            breadcrumb = "بیشتر › مدیریت › تنظیم ترم‌ها › بازبینی"
            intro = "پیش از اعمال روی همه کلاس‌های این دانشگاه، اطلاعات را بررسی کنید."
            confirm_label = "✅ اعمال روی همه کلاس‌ها"
        rows = (
            (Button(confirm_label, self._cb("trmconf")),),
            (Button("↩️ مرحله قبل", self._cb("trmback")), Button("❌ لغو", self._cb("trmcxl"))),
        )
        return semantic_screen(
            title,
            "term_wizard_review" if answers.get("mode") != "class_term" else "class_term_wizard_review",
            breadcrumb=breadcrumb,
            intro=intro,
            facts=facts,
            rows=rows,
        )

    def _term_wizard_expired(self):
        return ActionResult(
            warning_screen(
                "این فرآیند دیگر در دسترس نیست. دوباره شروع کنید.",
                title="📅 ترم",
                kind="term_wizard_expired",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def term_wizard_text(self, subject: str, text: str, private: bool = True):
        """Advance an in-progress institution-term or class-term-override
        wizard with free text, or return None -- same contract as
        class_wizard_text."""
        if not private:
            return None
        wizard = self.state.term_wizard(self.platform, subject)
        if wizard is None:
            return None
        answers = wizard["answers"]
        steps = self._term_wizard_steps(answers)
        index = wizard["step_index"]
        if index >= len(steps):
            return ActionResult(self._term_wizard_review_screen(answers))
        key = steps[index]
        if key == "term_key":
            value, error = _validate_term_key(text)
        elif key == "name":
            value, error = _validate_class_text(text, 1, 160)
        else:
            value, error = _validate_jalali_date(text)
            if error is None and key == "ends_on" and value < str(answers.get("starts_on") or ""):
                value, error = None, "تاریخ پایان نمی‌تواند قبل از تاریخ شروع باشد."
        if error:
            return ActionResult(self._term_wizard_step_screen(steps, index, answers, error=error))
        next_answers = dict(answers)
        next_answers[key] = value
        next_index = index + 1
        self.state.advance_term_wizard(self.platform, subject, next_index, next_answers)
        if next_index >= len(steps):
            return ActionResult(self._term_wizard_review_screen(next_answers))
        return ActionResult(self._term_wizard_step_screen(steps, next_index, next_answers))

    def term_wizard_back(self, subject: str):
        wizard = self.state.term_wizard(self.platform, subject)
        if wizard is None:
            return self._term_wizard_expired()
        steps = self._term_wizard_steps(wizard["answers"])
        index = max(0, wizard["step_index"] - 1)
        self.state.advance_term_wizard(self.platform, subject, index, wizard["answers"])
        return ActionResult(self._term_wizard_step_screen(steps, index, wizard["answers"]))

    def term_wizard_cancel(self, subject: str):
        wizard = self.state.term_wizard(self.platform, subject)
        is_class_term = bool(wizard and wizard["answers"].get("mode") == "class_term")
        self.state.cancel_term_wizard(self.platform, subject)
        if is_class_term:
            return ActionResult(
                semantic_screen(
                    "✏️ ویرایش ترم",
                    "class_term_wizard_cancelled",
                    intro="ویرایش ترم لغو شد.",
                    rows=self._nav_rows(back_action="more", back_label="‹ بیشتر"),
                )
            )
        return ActionResult(
            semantic_screen(
                "📅 تنظیم ترم‌ها",
                "term_wizard_cancelled",
                intro="تنظیم ترم لغو شد.",
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    def term_wizard_confirm(self, subject: str, private: bool):
        if self.platform != "telegram" or not private:
            return self._term_wizard_expired()
        wizard = self.state.term_wizard(self.platform, subject)
        if wizard is None:
            return self._term_wizard_expired()
        answers = wizard["answers"]
        steps = self._term_wizard_steps(answers)
        if wizard["step_index"] < len(steps):
            return ActionResult(self._term_wizard_step_screen(steps, wizard["step_index"], answers))
        if answers.get("mode") == "class_term":
            return self._class_term_wizard_confirm(subject, answers)
        return self._institution_term_wizard_confirm(subject, answers)

    def _institution_term_wizard_confirm(self, subject: str, answers: dict):
        institution_id = str(answers.get("institution_id") or "")
        if not is_uuid(institution_id):
            return self._term_wizard_expired()
        try:
            result = self.backend.institution_terms_set(
                self.platform, subject, institution_id,
                str(answers.get("term_key") or ""), str(answers.get("name") or ""),
                str(answers.get("starts_on") or ""), str(answers.get("ends_on") or ""),
            )
        except Exception as exc:
            logging.error(
                "institution term set failed type=%s message=%s",
                type(exc).__name__,
                exc,
            )
            message = (
                error_message(exc.code, exc.status)
                if isinstance(exc, FanoosApiError)
                else "تنظیم ترم ناموفق بود. دوباره امتحان کنید."
            )
            return ActionResult(
                error_screen(
                    message,
                    title="📅 تنظیم ترم‌ها",
                    kind="term_wizard_failed",
                    rows=(
                        (Button("🔁 تلاش دوباره", self._cb("trmconf")),),
                    )
                    + self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
                )
            )
        self.state.cancel_term_wizard(self.platform, subject)
        applied = to_persian_digits(result.get("applied_count") or 0)
        skipped = to_persian_digits(result.get("skipped_count") or 0)
        return ActionResult(
            semantic_screen(
                "✅ ترم اعمال شد",
                "term_wizard_created",
                severity="success",
                breadcrumb="بیشتر › مدیریت › تنظیم ترم‌ها",
                intro="ترم روی کلاس‌های فعال این دانشگاه اعمال شد.",
                facts=[
                    ("کلاس‌های به‌روزشده", applied),
                    ("کلاس‌های دارای تاریخ اختصاصی (نادیده‌گرفته‌شده)", skipped),
                ],
                rows=self._nav_rows(back_action="manage", back_label="‹ مدیریت"),
            )
        )

    # -- Class term dates (representative) -----------------------------------
    # A representative's own view of their class's term dates, alongside
    # their pending-requests view. The entry point probes academic_terms_list
    # (requires academic.view, workspace-scoped) purely to decide what to
    # show; can_override in the response is a separate, non-throwing read of
    # academic.manage, and POST /academic-terms/override re-checks it
    # server-side regardless of what this screen showed -- the bot only ever
    # hides what the backend has already told it is unavailable.

    def class_terms(self, subject: str):
        try:
            _, selected, blocked = self._workspace_or_result(subject)
            if blocked:
                return blocked
            page = self.backend.academic_terms_list(self.platform, subject, selected)
        except Exception as exc:
            return self._error(exc)
        items = [
            item for item in page.get("items") or []
            if isinstance(item, dict) and is_uuid(str(item.get("id") or ""))
        ]
        can_override = page.get("can_override") is True
        if not items:
            return ActionResult(
                semantic_screen(
                    "📅 ترم‌های کلاس",
                    "class_terms_empty",
                    breadcrumb="بیشتر › ترم‌های کلاس",
                    intro="هنوز ترمی برای این کلاس ثبت نشده است.",
                    rows=self._nav_rows(back_action="more", back_label="‹ بیشتر"),
                )
            )

        def row_label(item: dict) -> str:
            starts = format_jalali_date(str(item.get("starts_on") or ""))
            ends = format_jalali_date(str(item.get("ends_on") or ""))
            badge = "✏️" if item.get("origin") == "override" else "🏫"
            name = truncate_text(str(item.get("name") or item.get("term_key") or ""), 50)
            return truncate_text(f"{badge} {name} ({starts} تا {ends})", 90)

        if can_override:
            rows: tuple[tuple[Button, ...], ...] = tuple(
                (Button(
                    row_label(item),
                    self._route_callback(subject, "class_term_pick", "actovr", {
                        "workspace_id": selected,
                        "term_key": str(item.get("term_key") or ""),
                        "name": str(item.get("name") or ""),
                        "status": str(item.get("status") or "planned"),
                        "starts_on": str(item.get("starts_on") or ""),
                    }),
                ),)
                for item in items
            )
        else:
            rows = tuple((Button(row_label(item), self._cb("acterms")),) for item in items)
        rows += self._nav_rows(back_action="more", back_label="‹ بیشتر")
        return ActionResult(
            semantic_screen(
                "📅 ترم‌های کلاس",
                "class_terms",
                breadcrumb="بیشتر › ترم‌های کلاس",
                intro=(
                    "برای ویرایش تاریخ یک ترم، آن را انتخاب کنید."
                    if can_override
                    else "ترم‌های این کلاس؛ فقط نماینده کلاس می‌تواند تاریخ‌ها را تغییر دهد."
                ),
                rows=rows,
            )
        )

    def class_term_pick(self, subject: str, ref: str):
        payload = self._route_payload(subject, ref, "class_term_pick")
        if not payload or not is_uuid(str(payload.get("workspace_id") or "")):
            return self._expired_route()
        answers = {
            "mode": "class_term",
            "workspace_id": str(payload["workspace_id"]),
            "term_key": str(payload.get("term_key") or ""),
            "name": str(payload.get("name") or ""),
            "status": str(payload.get("status") or "planned"),
        }
        self.state.start_term_wizard(self.platform, subject, answers)
        return ActionResult(self._term_wizard_step_screen(self._CLASS_TERM_STEP_ORDER, 0, answers))

    def _class_term_wizard_confirm(self, subject: str, answers: dict):
        workspace_id = str(answers.get("workspace_id") or "")
        if not is_uuid(workspace_id):
            return self._term_wizard_expired()
        try:
            self.backend.academic_terms_override(
                self.platform, subject, workspace_id, str(answers.get("term_key") or ""),
                str(answers.get("name") or ""), str(answers.get("starts_on") or ""),
                str(answers.get("ends_on") or ""), str(answers.get("status") or "planned"),
            )
        except Exception as exc:
            logging.error(
                "class term override failed type=%s message=%s",
                type(exc).__name__,
                exc,
            )
            message = (
                error_message(exc.code, exc.status)
                if isinstance(exc, FanoosApiError)
                else "ویرایش ترم ناموفق بود. دوباره امتحان کنید."
            )
            return ActionResult(
                error_screen(
                    message,
                    title="✏️ ویرایش ترم",
                    kind="class_term_wizard_failed",
                    rows=(
                        (Button("🔁 تلاش دوباره", self._cb("trmconf")),),
                    )
                    + self._nav_rows(back_action="more", back_label="‹ بیشتر"),
                )
            )
        self.state.cancel_term_wizard(self.platform, subject)
        return ActionResult(
            semantic_screen(
                "✅ ترم ذخیره شد",
                "class_term_wizard_created",
                severity="success",
                breadcrumb="بیشتر › ترم‌های کلاس",
                intro="تاریخ‌های تازه این ترم برای کلاس شما ذخیره شد.",
                rows=self._nav_rows(back_action="more", back_label="‹ بیشتر"),
            )
        )

    # -- Student join wizard -------------------------------------------------
    # Reply-keyboard exception to the rest of the bot (owner's explicit
    # request); see join_wizard.py's module docstring for the three
    # deliberate differences from legacy/bot/dent_bot/onboarding.py. Methods
    # here return jw.RawKeyboardSend / jw.RawKeyboardHandoff / None (not
    # ActionResult) -- the runtime special-cases these to call botapi's
    # reply-keyboard methods directly, bypassing the ui_v3 Screen/deliver()
    # pipeline, which is structurally inline-keyboard-only.

    def _join_wizard_azad(self, answers: dict) -> bool:
        return answers.get("institution_type") == "azad_university"

    def _join_wizard_next_step(self, step: str, azad: bool) -> str:
        index = jw.STEP_ORDER.index(step)
        next_step = jw.STEP_ORDER[index + 1]
        if next_step == "course-type" and azad:
            return jw.STEP_ORDER[index + 2]
        return next_step

    def _join_wizard_cancelled_result(self):
        return ActionResult(
            semantic_screen(
                "عضویت در کلاس",
                "join_wizard_cancelled",
                intro="فرآیند عضویت لغو شد. اطلاعات واردشده ذخیره نشد.",
                rows=self._nav_rows(),
            )
        )

    def join_wizard_begin(self, subject: str, private: bool):
        if not private:
            return jw.RawKeyboardHandoff(
                handoff=ActionResult(
                    warning_screen(
                        "برای عضویت در کلاس، این گفت‌وگو را به‌صورت خصوصی با ربات ادامه بده.",
                        title="عضویت در کلاس",
                        kind="join_wizard_private_required",
                        rows=self._nav_rows(),
                    )
                )
            )
        first_step = jw.STEP_ORDER[0]
        self.state.start_join_wizard(self.platform, subject, first_step)
        return self._join_wizard_render(subject, first_step, [], {})

    def join_wizard_text(self, subject: str, text: str, private: bool = True):
        """Advance an in-progress join wizard with free text (including a
        reply-keyboard button's label, which arrives as ordinary text), or
        return None. None means "no active wizard for this subject": the
        caller (bot runtime) should fall through to normal dispatch."""
        if not private:
            return None
        wizard = self.state.join_wizard(self.platform, subject)
        if wizard is None:
            return None
        step = wizard["step"]
        history = list(wizard["history"])
        answers = dict(wizard["answers"])
        raw = (text or "").strip()

        if raw == jw.CANCEL:
            self.state.cancel_join_wizard(self.platform, subject)
            return jw.RawKeyboardHandoff(handoff=self._join_wizard_cancelled_result())
        if raw == jw.BACK_STEP or (step == "otp" and raw == jw.CHANGE_PHONE):
            return self._join_wizard_back(subject, history, answers)
        if step == "review" and raw == jw.RESTART_PROFILE:
            first_step = jw.STEP_ORDER[0]
            self.state.start_join_wizard(self.platform, subject, first_step)
            return self._join_wizard_render(subject, first_step, [], {})

        return self._join_wizard_handle_input(subject, step, history, answers, raw)

    def join_wizard_contact(self, subject: str, phone_number: str, private: bool = True):
        """A Telegram/Bale contact-share message. Only meaningful at the
        'contact' step; returns None otherwise so the runtime's normal
        dispatch is unaffected (same None-means-inactive contract as
        join_wizard_text)."""
        if not private:
            return None
        wizard = self.state.join_wizard(self.platform, subject)
        if wizard is None or wizard["step"] != "contact":
            return None
        answers = dict(wizard["answers"])
        try:
            issued = self.backend.onboarding_otp_request(self.platform, subject, str(phone_number or ""))
        except Exception as exc:
            return jw.RawKeyboardHandoff(handoff=self._error(exc))
        answers["phone_masked"] = str(issued.get("phone_masked") or "")
        answers["challenge_token"] = str(issued.get("challenge_token") or "")
        new_history = wizard["history"] + ["contact"]
        self.state.advance_join_wizard(self.platform, subject, "otp", new_history, answers)
        return jw.RawKeyboardSend(jw.otp_screen(answers["phone_masked"]))

    def _join_wizard_back(self, subject: str, history: list[str], answers: dict):
        if not history:
            self.state.cancel_join_wizard(self.platform, subject)
            return jw.RawKeyboardHandoff(handoff=self._join_wizard_cancelled_result())
        previous_step = history[-1]
        new_history = history[:-1]
        answers = dict(answers)
        answers.pop("_cursors", None)
        answers.pop("_page", None)
        self.state.advance_join_wizard(self.platform, subject, previous_step, new_history, answers)
        return self._join_wizard_render(subject, previous_step, new_history, answers)

    def _join_wizard_go_forward(self, subject: str, step: str, history: list[str], answers: dict):
        azad = self._join_wizard_azad(answers)
        next_step = self._join_wizard_next_step(step, azad)
        new_history = history + [step]
        answers = dict(answers)
        answers.pop("_cursors", None)
        answers.pop("_page", None)
        self.state.advance_join_wizard(self.platform, subject, next_step, new_history, answers)
        return self._join_wizard_render(subject, next_step, new_history, answers)

    def _join_wizard_render(self, subject: str, step: str, history: list[str], answers: dict):
        if step in ("province", "institution", "faculty", "program"):
            try:
                page, items = self._join_wizard_fetch_page(step, answers, page_delta=0)
            except Exception as exc:
                return jw.RawKeyboardHandoff(handoff=self._error(exc))
            return self._join_wizard_render_list_result(step, page, items, answers)
        azad = self._join_wizard_azad(answers)
        if step == "first-name":
            return jw.RawKeyboardSend(jw.first_name_screen())
        if step == "last-name":
            return jw.RawKeyboardSend(jw.last_name_screen())
        if step == "entry-year":
            return jw.RawKeyboardSend(jw.entry_year_screen(azad=azad))
        if step == "entry-term":
            return jw.RawKeyboardSend(jw.entry_term_screen(azad=azad))
        if step == "course-type":
            return jw.RawKeyboardSend(jw.course_type_screen())
        if step == "student-number":
            return jw.RawKeyboardSend(jw.student_number_screen(azad=azad))
        if step == "review":
            return jw.RawKeyboardSend(jw.review_screen(answers, azad=azad))
        if step == "contact":
            return jw.RawKeyboardSend(jw.contact_screen())
        if step == "otp":
            return jw.RawKeyboardSend(jw.otp_screen(str(answers.get("phone_masked") or "")))
        if step == "awaiting-creation-decision":
            return jw.RawKeyboardSend(jw.class_not_found_screen())
        return jw.RawKeyboardHandoff(
            handoff=ActionResult(
                warning_screen("این مرحله دیگر در دسترس نیست.", kind="join_wizard_expired", rows=self._nav_rows())
            )
        )

    def _join_wizard_fetch_page(self, step: str, answers: dict, *, page_delta: int):
        cursors = list(answers.get("_cursors") or [None])
        page_index = max(0, int(answers.get("_page") or 0) + page_delta)
        if page_index >= len(cursors):
            page_index = len(cursors) - 1
        cursor = cursors[page_index]
        if step == "province":
            result = self.backend.directory_provinces(self.platform, jw.PAGE_SIZE, cursor)
        elif step == "institution":
            result = self.backend.directory_institutions(self.platform, answers.get("province_id", ""), jw.PAGE_SIZE, cursor)
        elif step == "faculty":
            result = self.backend.directory_faculties(self.platform, answers.get("institution_id", ""), jw.PAGE_SIZE, cursor)
        elif step == "program":
            result = self.backend.directory_programs(self.platform, answers.get("faculty_id", ""), jw.PAGE_SIZE, cursor)
        else:
            raise ValueError(f"not a listing step: {step}")
        items = list(result.get("items") or [])
        next_cursor = self._clean_cursor(result.get("next_cursor"))
        if next_cursor and len(cursors) == page_index + 1:
            cursors = cursors + [next_cursor]
        answers["_cursors"] = cursors
        answers["_page"] = page_index
        page = jw.ListPage(items=items, page_index=page_index, has_previous=page_index > 0, has_next=next_cursor is not None)
        return page, items

    def _join_wizard_render_list_result(self, step: str, page, items: list, answers: dict):
        if not items and page.page_index == 0:
            messages = {
                "faculty": f"برای {answers.get('institution_name') or 'این دانشگاه'} هنوز دانشکده‌ای ثبت نشده.",
                "program": f"برای {answers.get('faculty_name') or 'این دانشکده'} هنوز رشته‌ای ثبت نشده.",
            }
            return jw.RawKeyboardSend(jw.empty_list_screen(messages.get(step, "موردی برای انتخاب پیدا نشد.")))
        if step == "province":
            return jw.RawKeyboardSend(jw.province_screen(page))
        if step == "institution":
            return jw.RawKeyboardSend(jw.institution_screen(page, str(answers.get("province_name") or "")))
        azad = self._join_wizard_azad(answers)
        if step == "faculty":
            return jw.RawKeyboardSend(jw.faculty_screen(page, str(answers.get("institution_name") or ""), azad=azad))
        return jw.RawKeyboardSend(jw.program_screen(page, str(answers.get("faculty_name") or ""), azad=azad))

    def _join_wizard_handle_list_input(self, subject: str, step: str, history: list[str], answers: dict, raw: str):
        delta = 1 if raw == jw.NEXT_PAGE else (-1 if raw == jw.PREVIOUS_PAGE else 0)
        try:
            page, items = self._join_wizard_fetch_page(step, answers, page_delta=delta)
        except Exception as exc:
            return jw.RawKeyboardHandoff(handoff=self._error(exc))
        if delta != 0:
            self.state.advance_join_wizard(self.platform, subject, step, history, answers)
            return self._join_wizard_render_list_result(step, page, items, answers)
        matched = next((item for item in items if str(item.get("name")) == raw), None)
        if matched is None:
            self.state.advance_join_wizard(self.platform, subject, step, history, answers)
            return self._join_wizard_render_list_result(step, page, items, answers)
        if step == "province":
            answers["province_id"] = str(matched.get("id") or "")
            answers["province_name"] = str(matched.get("name") or "")
        elif step == "institution":
            answers["institution_id"] = str(matched.get("id") or "")
            answers["institution_name"] = str(matched.get("name") or "")
            answers["institution_type"] = str(matched.get("institution_type") or "")
        elif step == "faculty":
            answers["faculty_id"] = str(matched.get("id") or "")
            answers["faculty_name"] = str(matched.get("name") or "")
        elif step == "program":
            answers["program_id"] = str(matched.get("id") or "")
            answers["program_name"] = str(matched.get("name") or "")
        return self._join_wizard_go_forward(subject, step, history, answers)

    def _join_wizard_handle_input(self, subject: str, step: str, history: list[str], answers: dict, raw: str):
        if step in ("province", "institution", "faculty", "program"):
            return self._join_wizard_handle_list_input(subject, step, history, answers, raw)
        if step == "first-name":
            value = jw.clean_text(raw, 64)
            if len(value) < 2:
                return jw.RawKeyboardSend(jw.first_name_screen())
            answers["first_name"] = value
            return self._join_wizard_go_forward(subject, step, history, answers)
        if step == "last-name":
            value = jw.clean_text(raw, 64)
            if len(value) < 2:
                return jw.RawKeyboardSend(jw.last_name_screen())
            answers["last_name"] = value
            return self._join_wizard_go_forward(subject, step, history, answers)
        if step == "entry-year":
            if raw not in jw.ENTRY_YEARS:
                return self._join_wizard_render(subject, step, history, answers)
            answers["entry_year_fa"] = raw
            answers["entry_year"] = int(jw.normalize_digits(raw))
            return self._join_wizard_go_forward(subject, step, history, answers)
        if step == "entry-term":
            if raw not in jw.ENTRY_TERMS:
                return self._join_wizard_render(subject, step, history, answers)
            answers["entry_term"] = raw
            return self._join_wizard_go_forward(subject, step, history, answers)
        if step == "course-type":
            if raw not in jw.COURSE_TYPES:
                return self._join_wizard_render(subject, step, history, answers)
            answers["course_type"] = raw
            return self._join_wizard_go_forward(subject, step, history, answers)
        if step == "student-number":
            if raw == jw.SKIP_STUDENT_NUMBER:
                answers["student_number"] = ""
                return self._join_wizard_go_forward(subject, step, history, answers)
            number = jw.normalize_student_number(raw)
            if not re.fullmatch(r"[0-9]{5,20}", number):
                return jw.RawKeyboardSend(jw.student_number_screen(azad=self._join_wizard_azad(answers)))
            answers["student_number"] = number
            return self._join_wizard_go_forward(subject, step, history, answers)
        if step == "review":
            if raw == jw.CONFIRM_PROFILE:
                return self._join_wizard_go_forward(subject, step, history, answers)
            return self._join_wizard_render(subject, step, history, answers)
        if step == "contact":
            # A typed phone number is not proof of ownership; only the
            # request_contact button (join_wizard_contact) starts the OTP.
            return self._join_wizard_render(subject, step, history, answers)
        if step == "otp":
            return self._join_wizard_handle_otp_text(subject, history, answers, raw)
        if step == "awaiting-creation-decision":
            return self._join_wizard_handle_creation_decision(subject, answers, raw)
        return self._join_wizard_render(subject, step, history, answers)

    def _join_wizard_handle_otp_text(self, subject: str, history: list[str], answers: dict, raw: str):
        if raw == jw.RESEND_OTP:
            try:
                issued = self.backend.onboarding_otp_resend(self.platform, subject, str(answers.get("challenge_token") or ""))
            except FanoosApiError as exc:
                return jw.RawKeyboardSend(jw.otp_screen(str(answers.get("phone_masked") or ""), error=error_message(exc.code, exc.status)))
            except Exception as exc:
                return jw.RawKeyboardHandoff(handoff=self._error(exc))
            answers["challenge_token"] = str(issued.get("challenge_token") or "")
            answers["phone_masked"] = str(issued.get("phone_masked") or "")
            self.state.advance_join_wizard(self.platform, subject, "otp", history, answers)
            return jw.RawKeyboardSend(jw.otp_screen(answers["phone_masked"]))
        code = jw.normalize_digits(raw)
        if not re.fullmatch(r"[0-9]{4,8}", code):
            return jw.RawKeyboardSend(jw.otp_screen(str(answers.get("phone_masked") or "")))
        try:
            self.backend.onboarding_otp_verify(self.platform, subject, str(answers.get("challenge_token") or ""), code)
        except FanoosApiError as exc:
            # OnboardingPhoneVerificationService enforces expiry/attempts/used-code
            # rules; surface exactly what it says rather than masking it behind a
            # generic failure (wrong code vs. attempts exceeded are different facts).
            return jw.RawKeyboardSend(jw.otp_screen(str(answers.get("phone_masked") or ""), error=error_message(exc.code, exc.status)))
        except Exception as exc:
            return jw.RawKeyboardHandoff(handoff=self._error(exc))
        return self._join_wizard_finish(subject, history, answers)

    def _join_wizard_finish(self, subject: str, history: list[str], answers: dict):
        program_id = str(answers.get("program_id") or "")
        entry_year = int(answers.get("entry_year") or 0)
        try:
            result = self.backend.onboarding_join(self.platform, subject, program_id, entry_year)
        except Exception as exc:
            return jw.RawKeyboardHandoff(handoff=self._error(exc))
        if result.get("status") == "joined":
            self.state.cancel_join_wizard(self.platform, subject)
            workspace_name = str(result.get("workspace_name") or "")
            return jw.RawKeyboardHandoff(handoff=self.home(subject, notice=jw.success_notice(workspace_name)))
        new_history = history + ["otp"]
        self.state.advance_join_wizard(self.platform, subject, "awaiting-creation-decision", new_history, answers)
        return jw.RawKeyboardSend(jw.class_not_found_screen())

    def _join_wizard_handle_creation_decision(self, subject: str, answers: dict, raw: str):
        if raw == jw.REQUEST_CLASS_CREATION:
            program_id = str(answers.get("program_id") or "")
            entry_year = int(answers.get("entry_year") or 0)
            try:
                self.backend.onboarding_class_creation_request(self.platform, subject, program_id, entry_year)
            except Exception as exc:
                return jw.RawKeyboardHandoff(handoff=self._error(exc))
            self.state.cancel_join_wizard(self.platform, subject)
            return jw.RawKeyboardHandoff(handoff=self.home(subject, notice=jw.class_creation_recorded_notice()))
        return jw.RawKeyboardSend(jw.class_not_found_screen())

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
        if action == "joinupgrade":
            return self.join_wizard_request_upgrade(subject)
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
        if action == "clsnew":
            return self.class_wizard_begin(subject, private)
        if action == "clsback":
            return self.class_wizard_back(subject)
        if action == "clscancel":
            return self.class_wizard_cancel(subject)
        if action == "clsconfirm":
            return self.class_wizard_confirm(subject, private)
        if action == "repnew":
            return self.appoint_wizard_begin(subject, private)
        if action == "apback":
            return self.appoint_wizard_back(subject)
        if action == "apcancel":
            return self.appoint_wizard_cancel(subject)
        if action == "apconfirm":
            return self.appoint_wizard_confirm(subject, private)
        if action == "apws" and ref:
            return self.appoint_wizard_pick_workspace(subject, ref)
        if action == "apwsp" and ref:
            return self.appoint_wizard_page_workspaces(subject, ref)
        if action == "apcd" and ref:
            return self.appoint_wizard_pick_candidate(subject, ref)
        if action == "apcdp" and ref:
            return self.appoint_wizard_page_candidates(subject, ref)
        if action == "cqlist":
            return self.class_creation_requests_begin(subject, private)
        if action == "cqpage" and ref:
            return self.class_creation_requests_page(subject, ref)
        if action == "cqpick" and ref:
            return self.class_creation_request_pick(subject, ref)
        if action == "cqdno" and ref:
            return self.class_creation_request_pick(subject, ref)
        if action == "cqappr" and ref:
            return self.class_creation_request_approve_begin(subject, ref)
        if action == "cqaback":
            return self.class_creation_request_wizard_back(subject)
        if action == "cqacxl":
            return self.class_creation_request_wizard_cancel(subject)
        if action == "cqaconf":
            return self.class_creation_request_wizard_confirm(subject, private)
        if action == "cqdecl" and ref:
            return self.class_creation_request_decline_confirm(subject, ref)
        if action == "cqdyes" and ref:
            return self.class_creation_request_decline_do(subject, ref)
        if action == "trmlist":
            return self.institution_terms_begin(subject, private)
        if action == "trmpage" and ref:
            return self.institution_terms_page(subject, ref)
        if action == "trmpick" and ref:
            return self.institution_term_pick(subject, ref)
        if action == "trmback":
            return self.term_wizard_back(subject)
        if action == "trmcxl":
            return self.term_wizard_cancel(subject)
        if action == "trmconf":
            return self.term_wizard_confirm(subject, private)
        if action == "acterms":
            return self.class_terms(subject)
        if action == "actovr" and ref:
            return self.class_term_pick(subject, ref)
        if action == "reprequests":
            return self.representative_requests(subject)
        if action == "repappr" and ref:
            return self.representative_request_approve(subject, ref)
        if action == "repdecl" and ref:
            return self.representative_request_decline(subject, ref)
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
