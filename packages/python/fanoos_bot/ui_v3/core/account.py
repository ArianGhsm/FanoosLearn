from __future__ import annotations

from .actions import (
    back_action,
    cancel_action,
    home_action,
    unlink_confirm_action,
    unlink_request_action,
    website_action,
    workspace_action,
)
from .contracts import ActionRow, Context, EditPolicy, Fact, Screen, Section, Severity


def linked_account_screen(
    *,
    platform_label: str,
    website_url: str,
    active_workspace_label: str | None = None,
    workspace_count_label: str | None = None,
) -> Screen:
    facts = [Fact("وضعیت", "متصل ✅"), Fact("پیام‌رسان", platform_label)]
    if workspace_count_label:
        facts.append(Fact("فضاهای آموزشی", workspace_count_label))
    if active_workspace_label:
        facts.append(Fact("فضای فعال", active_workspace_label))
    else:
        facts.append(Fact("فضای فعال", "انتخاب نشده"))
    return Screen(
        identifier="account.linked",
        title="👤 حساب",
        intro="اتصال این پیام‌رسان به حساب فانوس فعال است.",
        context=Context("حساب", "متصل"),
        sections=(Section(facts=tuple(facts)),),
        action_rows=(
            ActionRow((workspace_action(), website_action(website_url))),
            ActionRow((unlink_request_action(),)),
            ActionRow((back_action(), home_action())),
        ),
    )


def unlink_confirmation_screen(*, platform_label: str) -> Screen:
    return Screen(
        identifier="account.unlink_confirm",
        title="⚠️ قطع اتصال پیام‌رسان",
        intro=f"اتصال {platform_label} به حساب فانوس قطع شود؟",
        severity=Severity.WARNING,
        sections=(
            Section(
                body="این کار فقط اتصال همین پیام‌رسان را حذف می‌کند؛ حساب اصلی فانوس حذف نمی‌شود. برای استفاده دوباره باید اتصال تازه‌ای بسازید."
            ),
        ),
        action_rows=(
            ActionRow((unlink_confirm_action(),)),
            ActionRow((cancel_action(),)),
            ActionRow((home_action(),)),
        ),
        edit_policy=EditPolicy.SEND_NEW,
    )


def unlink_success_screen(website_url: str) -> Screen:
    return Screen(
        identifier="account.unlink_success",
        title="✅ اتصال قطع شد",
        intro="این پیام‌رسان دیگر به حساب فانوس متصل نیست. حساب اصلی فانوس شما تغییری نکرده است.",
        severity=Severity.SUCCESS,
        action_rows=(
            ActionRow((website_action(website_url),)),
            ActionRow((home_action(),)),
        ),
        edit_policy=EditPolicy.SEND_NEW,
    )
