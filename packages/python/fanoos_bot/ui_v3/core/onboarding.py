from __future__ import annotations

from .actions import help_action, home_action, website_action
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
