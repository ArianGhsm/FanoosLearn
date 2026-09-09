from __future__ import annotations

from .contracts import Action, ActionRow, CallbackIntent


ACTION_HOME = "core.home"
ACTION_BACK = "core.back"
ACTION_CANCEL = "core.cancel"
ACTION_RETRY = "core.retry"
ACTION_WEBSITE = "core.website.open"
ACTION_HELP = "core.help"
ACTION_MORE = "core.more"
ACTION_COURSES = "core.courses"
ACTION_SCHEDULE = "core.schedule"
ACTION_GRADES = "core.grades"
ACTION_NOTIFICATIONS = "core.notifications"
ACTION_RESOURCES = "core.resources"
ACTION_ASSESSMENTS = "core.assessments"
ACTION_WORKSPACE = "core.workspace"
ACTION_WORKSPACE_SELECT = "core.workspace.select"
ACTION_ACCOUNT = "core.account"
ACTION_ACCOUNT_UNLINK_REQUEST = "core.account.unlink.request"
ACTION_ACCOUNT_UNLINK_CONFIRM = "core.account.unlink.confirm"


def _callback(identifier: str, label: str, name: str, *, params=(), destructive: bool = False) -> Action:
    return Action(identifier, label, intent=CallbackIntent(name, tuple(params)), destructive=destructive)


def home_action() -> Action:
    return _callback(ACTION_HOME, "🏠 خانه", "home")


def back_action() -> Action:
    return _callback(ACTION_BACK, "بازگشت", "back")


def cancel_action() -> Action:
    return _callback(ACTION_CANCEL, "لغو", "cancel")


def retry_action() -> Action:
    return _callback(ACTION_RETRY, "🔄 تلاش دوباره", "retry")


def website_action(url: str) -> Action:
    return Action(ACTION_WEBSITE, "🌐 باز کردن فانوس", url=url)


def help_action() -> Action:
    return _callback(ACTION_HELP, "ℹ️ راهنمای شروع", "help")


def more_action() -> Action:
    return _callback(ACTION_MORE, "بیشتر", "more")


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


def home_row() -> ActionRow:
    return ActionRow((home_action(),))


def back_home_row() -> ActionRow:
    return ActionRow((back_action(), home_action()))
