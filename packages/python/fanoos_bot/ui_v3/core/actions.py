from __future__ import annotations

from .contracts import Action, CallbackIntent


ACTION_HOME = "core.home"
ACTION_BACK = "core.back"
ACTION_CANCEL = "core.cancel"
ACTION_RETRY = "core.retry"
ACTION_WEBSITE = "core.website.open"
ACTION_HELP = "core.help"
ACTION_COURSES = "core.courses"
ACTION_SCHEDULE = "core.schedule"
ACTION_GRADES = "core.grades"
ACTION_NOTIFICATIONS = "core.notifications"
ACTION_RESOURCES = "core.resources"
ACTION_ASSESSMENTS = "core.assessments"
ACTION_PAYMENTS = "core.payments"
ACTION_WORKSPACE = "core.workspace"
ACTION_WORKSPACE_SELECT = "core.workspace.select"
ACTION_WORKSPACE_PAGE = "core.workspace.page"
ACTION_ACCOUNT = "core.account"
ACTION_ACCOUNT_UNLINK_REQUEST = "core.account.unlink.request"
ACTION_ACCOUNT_UNLINK_CONFIRM = "core.account.unlink.confirm"
ACTION_SCHEDULE_TODAY = "core.schedule.today"
ACTION_JOIN_BEGIN = "core.join.begin"
ACTION_MANAGE = "core.manage"
ACTION_REP_REQUESTS = "core.rep.requests"


def _callback(identifier: str, label: str, name: str, *, params=(), destructive: bool = False) -> Action:
    return Action(identifier, label, intent=CallbackIntent(name, tuple(params)), destructive=destructive)


def home_action() -> Action:
    return _callback(ACTION_HOME, "🏠 خانه", "home")


def back_action() -> Action:
    return _callback(ACTION_BACK, "‹ بازگشت", "back")


def cancel_action() -> Action:
    return _callback(ACTION_CANCEL, "لغو", "cancel")


def retry_action() -> Action:
    return _callback(ACTION_RETRY, "🔄 تلاش دوباره", "retry")


def website_action(url: str) -> Action:
    return Action(ACTION_WEBSITE, "🌐 باز کردن فانوس", url=url)


def help_action() -> Action:
    return _callback(ACTION_HELP, "ℹ️ راهنمای شروع", "help")




def courses_action() -> Action:
    return _callback(ACTION_COURSES, "📚 درس‌ها", "courses")


def schedule_action() -> Action:
    return _callback(ACTION_SCHEDULE, "📅 برنامه", "schedule")


def grades_action() -> Action:
    return _callback(ACTION_GRADES, "🎓 نمرات", "grades")


def notifications_action() -> Action:
    return _callback(ACTION_NOTIFICATIONS, "🔔 اعلان‌ها", "notifications")


def resources_action() -> Action:
    return _callback(ACTION_RESOURCES, "📚 منابع", "resources")


def assessments_action() -> Action:
    return _callback(ACTION_ASSESSMENTS, "📝 آزمون‌ها", "assessments")


def payments_action() -> Action:
    return _callback(ACTION_PAYMENTS, "💳 خرید و دسترسی", "payments")


def workspace_action() -> Action:
    return _callback(ACTION_WORKSPACE, "🏫 فضای آموزشی", "ws.list")


def workspace_select_action(workspace_id: str, label: str) -> Action:
    compact_label = " ".join(label.split())
    if len(compact_label) > 30:
        compact_label = compact_label[:29].rstrip() + "…"
    return _callback(
        ACTION_WORKSPACE_SELECT,
        f"انتخاب {compact_label}",
        "ws.select",
        params=(("w", workspace_id),),
    )


def workspace_page_action(page: int, *, next_page: bool) -> Action:
    if isinstance(page, bool) or int(page) < 0:
        raise ValueError("workspace page must be non-negative")
    return _callback(
        ACTION_WORKSPACE_PAGE,
        "بعدی ›" if next_page else "‹ قبلی",
        "ws.page",
        params=(("p", str(int(page))),),
    )


def account_action() -> Action:
    return _callback(ACTION_ACCOUNT, "👤 حساب", "account")


def unlink_request_action() -> Action:
    return _callback(ACTION_ACCOUNT_UNLINK_REQUEST, "قطع اتصال", "acct.unlink.ask")


def unlink_confirm_action() -> Action:
    return _callback(
        ACTION_ACCOUNT_UNLINK_CONFIRM,
        "بله، قطع شود",
        "acct.unlink.do",
        destructive=True,
    )


def schedule_today_action() -> Action:
    """Reuses the existing academic.schedule.today intent (already registered
    for the schedule hub) so the shell's "امروز" shortcut goes through the
    same day_schedule() path rather than a second one."""
    return _callback(ACTION_SCHEDULE_TODAY, "📅 امروز", "academic.schedule.today")


def join_begin_action() -> Action:
    return _callback(ACTION_JOIN_BEGIN, "🎓 عضویت در کلاس جدید", "core.join.begin")


def manage_action() -> Action:
    return _callback(ACTION_MANAGE, "🛠 مدیریت", "core.manage")


def representative_requests_action() -> Action:
    return _callback(ACTION_REP_REQUESTS, "📋 درخواست‌های عضویت", "core.rep.requests")


