from __future__ import annotations

from dataclasses import dataclass
from enum import Enum

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
from .contracts import ActionRow, Context, ListItem, Screen, Section

_INTRO = "سرویس موردنظرت را از منوی زیر انتخاب کن."
_FOOTER = "اطلاعات شخصی فقط از حساب متصل و منبع رسمی نمایش داده می‌شود."


class SlotState(str, Enum):
    CONTENT = "content"
    EMPTY = "empty"
    UNAVAILABLE = "unavailable"


@dataclass(frozen=True)
class HomeSlot:
    title: str
    headline: str
    detail: str = ""
    state: SlotState = SlotState.CONTENT

    def __post_init__(self) -> None:
        if not self.title.strip() or not self.headline.strip():
            raise ValueError("home slot title and headline are required")

    def list_item(self) -> ListItem:
        marker = "" if self.state is SlotState.CONTENT else ("—" if self.state is SlotState.EMPTY else "⚠️")
        return ListItem(self.headline, self.detail, meta=self.title, marker=marker)


def home_screen(
    workspace_label: str,
    *,
    next_schedule: HomeSlot,
    latest_announcement: HomeSlot,
    is_owner: bool = False,
    show_representative_requests: bool = False,
    notice: str = "",
) -> Screen:
    """The bot's one home screen and main menu.

    Provenance: this layout and wording is ported from the legacy Dent bot's
    live home screen (legacy/bot/dent_bot/bot_home_classops_ux_v2.py's
    canonical_home_screen -- not classops_ux_v3.py, which never held a home
    screen). "دنت‌یار | ورودی ۱۴۰۲" becomes "دنت‌یار | {selected workspace}":
    the cohort year was the only hardcoded part, everything else is reused
    verbatim. Rows are wired to whatever FANOOS can already serve for real
    (courses, today's schedule, grades, assessments, resources, purchase,
    notifications, account, workspace switching, help, the join wizard) plus
    owner/representative rows shown only when the backend already granted
    that capability -- the same probe-and-hide pattern application.py's
    more() uses, never a client-side authorization decision.

    This replaced an earlier FANOOS-native home screen (bot-01's original
    active_home_screen); that screen and the ui_v3 modules it alone reached
    were removed once this one took over the only call site, so there is
    again exactly one home screen builder, not two that override each other.
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


__all__ = ["HomeSlot", "SlotState", "home_screen"]
