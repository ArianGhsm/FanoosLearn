from __future__ import annotations

from .actions import (
    account_action,
    assessments_action,
    courses_action,
    grades_action,
    help_action,
    join_begin_action,
    manage_action,
    notifications_action,
    payments_action,
    representative_requests_action,
    resources_action,
    schedule_today_action,
    workspace_action,
)
from .contracts import ActionRow, Context, Screen, Section
from .home import HomeSlot

_INTRO = "سرویس موردنظرت را از منوی زیر انتخاب کن."
_FOOTER = "اطلاعات شخصی فقط از حساب متصل و منبع رسمی نمایش داده می‌شود."


def legacy_home_screen(
    workspace_label: str,
    *,
    next_schedule: HomeSlot,
    latest_announcement: HomeSlot,
    is_owner: bool = False,
    show_representative_requests: bool = False,
    notice: str = "",
) -> Screen:
    """The bot's visible shell: legacy Dent-bot layout and wording
    (legacy/bot/dent_bot/bot_home_classops_ux_v2.py canonical_home_screen),
    ported onto FANOOS workspaces rather than one welded-in cohort.

    "دنت‌یار | ورودی ۱۴۰۲" becomes "دنت‌یار | {selected workspace}" -- the
    cohort year was the only hardcoded part, everything else is reused
    verbatim. Rows are wired to whatever FANOOS can already serve for real
    (courses, today's schedule, grades, assessments, resources, purchase,
    notifications, account, workspace switching, help, the join wizard) plus
    owner/representative rows shown only when the backend already granted
    that capability -- the same probe-and-hide pattern application.py's
    more() uses, never a client-side authorization decision.
    """
    intro = _INTRO
    if notice:
        intro = f"✅ {notice}\n\n{intro}"

    rows = [
        ActionRow((schedule_today_action(), courses_action())),
        ActionRow((grades_action(), assessments_action())),
        ActionRow((resources_action(), payments_action())),
        ActionRow((notifications_action(), account_action())),
        ActionRow((workspace_action(), help_action())),
        ActionRow((join_begin_action(),)),
    ]
    if is_owner:
        rows.append(ActionRow((manage_action(),)))
    if show_representative_requests:
        rows.append(ActionRow((representative_requests_action(),)))

    return Screen(
        identifier="home.active",
        title=f"دنت‌یار | {workspace_label}",
        intro=intro,
        context=Context("فضای آموزشی", workspace_label),
        sections=(
            Section(title="📅 برنامه", items=(next_schedule.list_item(),)),
            Section(title="📢 تازه", items=(latest_announcement.list_item(),)),
        ),
        action_rows=tuple(rows),
        footer=_FOOTER,
    )


__all__ = ["legacy_home_screen"]
