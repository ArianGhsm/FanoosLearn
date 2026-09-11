from __future__ import annotations

from .actions import (
    account_action,
    help_action,
    home_action,
    website_action,
    workspace_action,
    workspace_select_action,
)
from .contracts import ActionRow, Context, EditPolicy, Fact, ListItem, Screen, Section, Severity


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


def linked_no_workspace_screen(website_url: str) -> Screen:
    """Critical zero-workspace state: complete product shell with no fake create action."""

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
        ) + _website_row(website_url) + (ActionRow((home_action(),)),),
    )


def linked_one_workspace_screen(
    workspace_id: str,
    workspace_label: str,
    *,
    selected: bool,
    website_url: str,
) -> Screen:
    status = "فعال" if selected else "آماده انتخاب"
    primary = home_action() if selected else workspace_select_action(workspace_id, workspace_label)
    return Screen(
        identifier="onboarding.one_workspace",
        title="🏫 فضای آموزشی شما",
        intro="حساب متصل است و یک فضای آموزشی برای شما پیدا شد.",
        severity=Severity.SUCCESS if selected else Severity.INFO,
        context=Context("فضای آموزشی", workspace_label),
        sections=(Section(facts=(Fact("وضعیت", status),)),),
        action_rows=(ActionRow((primary,)), ActionRow((account_action(), help_action()))) + _website_row(website_url),
    )


def linked_multiple_workspaces_screen(website_url: str) -> Screen:
    return Screen(
        identifier="onboarding.multiple_workspaces",
        title="🏫 انتخاب فضای آموزشی",
        intro="حساب شما به بیش از یک فضای آموزشی دسترسی دارد. فضای موردنظر را انتخاب کنید.",
        severity=Severity.INFO,
        sections=(Section(body="فهرست و انتخاب واقعی در صفحه فضای آموزشی از backend تازه خوانده می‌شود."),),
        action_rows=(
            ActionRow((workspace_action(),)),
            ActionRow((account_action(), help_action())),
        ) + _website_row(website_url) + (ActionRow((home_action(),)),),
    )


def link_challenge_expired_screen(website_url: str) -> Screen:
    return Screen(
        identifier="onboarding.challenge_expired",
        title="⌛ درخواست اتصال منقضی شده",
        intro="این درخواست دیگر قابل استفاده نیست. از فانوس یک درخواست اتصال تازه بگیرید.",
        severity=Severity.WARNING,
        action_rows=_website_row(website_url) + (ActionRow((help_action(), home_action())),),
        edit_policy=EditPolicy.SEND_NEW,
    )


def onboarding_service_unavailable_screen(website_url: str) -> Screen:
    from .actions import retry_action

    return Screen(
        identifier="onboarding.service_unavailable",
        title="⚠️ اتصال به فانوس ممکن نیست",
        intro="در حال حاضر وضعیت حساب را نمی‌توان با اطمینان بررسی کرد.",
        severity=Severity.WARNING,
        sections=(Section(body="هیچ وضعیت قبلی به‌عنوان اطلاعات تازه نمایش داده نمی‌شود."),),
        action_rows=(ActionRow((retry_action(),)),) + _website_row(website_url) + (ActionRow((help_action(), home_action())),),
        edit_policy=EditPolicy.SEND_NEW,
    )


def getting_started_screen(website_url: str) -> Screen:
    return Screen(
        identifier="core.help",
        title="ℹ️ راهنمای شروع",
        intro="کارهای معمول فانوس از دکمه‌ها انجام می‌شوند و نیازی به حفظ دستورهای متنی ندارید.",
        sections=(
            Section(
                title="شروع سریع",
                items=(
                    ListItem("فضای آموزشی را بررسی یا انتخاب کنید.", marker="🏫"),
                    ListItem("از خانه وارد درس‌ها، برنامه، منابع یا نمرات شوید.", marker="🏠"),
                    ListItem("برای تنظیمات اتصال، بخش حساب را باز کنید.", marker="👤"),
                ),
            ),
        ),
        action_rows=(ActionRow((workspace_action(), account_action())),) + _website_row(website_url) + (ActionRow((home_action(),)),),
    )
