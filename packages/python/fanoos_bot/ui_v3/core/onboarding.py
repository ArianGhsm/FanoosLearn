from __future__ import annotations

from .actions import (
    account_action,
    help_action,
    home_action,
    more_action,
    website_action,
    workspace_action,
)
from .contracts import ActionRow, EditPolicy, ListItem, Screen, Section, Severity


def _website_row(website_url: str) -> tuple[ActionRow, ...]:
    return (ActionRow((website_action(website_url),)),) if website_url else ()


def unlinked_account_screen(website_url: str) -> Screen:
    return Screen(
        identifier="onboarding.unlinked",
        title="👋 خوش آمدید به فانوس",
        intro="این پیام‌رسان هنوز به حساب فانوس شما متصل نیست.",
        severity=Severity.INFO,
        sections=(
            Section(
                title="🔗 اتصال حساب",
                body="از وب‌سایت فانوس یک درخواست اتصال تازه بسازید و همان لینک یا کد را در این پیام‌رسان باز کنید.",
                items=(
                    ListItem("وارد حساب فانوس شوید."),
                    ListItem("از بخش حساب، اتصال پیام‌رسان را شروع کنید."),
                    ListItem("درخواست تازه را همین‌جا باز کنید."),
                ),
            ),
        ),
        action_rows=_website_row(website_url) + (ActionRow((help_action(), home_action())),),
        edit_policy=EditPolicy.SEND_NEW,
    )


def linked_no_workspace_screen(website_url: str, *, show_more: bool = False) -> Screen:
    """Critical zero-workspace state: complete product shell with no fake create action.

    `show_more` is purely a presentation switch: the caller decides, from backend
    authority, whether this viewer may reach the management entry point via
    more(). This function has no permission logic of its own.
    """

    return Screen(
        identifier="onboarding.linked_no_workspace",
        title="🏠 فانوس",
        intro="حساب شما متصل است ✅\nهنوز فضای آموزشی فعالی برای این حساب ندارید.",
        severity=Severity.INFO,
        sections=(
            Section(
                title="از اینجا چه کاری می‌توانید انجام دهید؟",
                body="عضویت فضای آموزشی از اطلاعات رسمی فانوس می‌آید. این ربات فضای آموزشی تازه‌ای ایجاد نمی‌کند.",
            ),
        ),
        action_rows=(
            ActionRow((workspace_action(),)),
            ActionRow((account_action(), help_action())),
        )
        + ((ActionRow((more_action(),)),) if show_more else ())
        + _website_row(website_url)
        + (ActionRow((home_action(),)),),
    )
