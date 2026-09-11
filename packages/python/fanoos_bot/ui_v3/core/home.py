from __future__ import annotations

from dataclasses import dataclass
from enum import Enum

from .actions import (
    account_action,
    assessments_action,
    courses_action,
    grades_action,
    help_action,
    home_action,
    more_action,
    notifications_action,
    payments_action,
    resources_action,
    schedule_action,
    website_action,
    workspace_action,
)
from .contracts import ActionRow, Context, ListItem, Screen, Section


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


def active_home_screen(
    workspace_label: str,
    *,
    next_schedule: HomeSlot,
    latest_announcement: HomeSlot,
    today_schedule: HomeSlot | None = None,
    date_label: str = "",
) -> Screen:
    """Build Home from explicit canonical content/empty/unavailable slot decisions."""

    schedule_items = (next_schedule.list_item(),)
    if today_schedule is not None:
        schedule_items += (today_schedule.list_item(),)

    return Screen(
        identifier="home.active",
        title="🏠 خانه",
        context=Context("فضای آموزشی", workspace_label, date_label),
        sections=(
            Section(title="📅 برنامه", items=schedule_items[:2]),
            Section(title="📢 تازه", items=(latest_announcement.list_item(),)),
        ),
        action_rows=(
            ActionRow((courses_action(), schedule_action())),
            ActionRow((grades_action(), notifications_action())),
            ActionRow((resources_action(), assessments_action())),
            ActionRow((payments_action(), account_action())),
            ActionRow((more_action(),)),
        ),
    )


def more_menu_screen(website_url: str) -> Screen:
    secondary_actions = (help_action(), website_action(website_url)) if website_url else (help_action(),)
    return Screen(
        identifier="core.more",
        title="بیشتر",
        intro="تنظیمات و مسیرهای تکمیلی فانوس.",
        action_rows=(
            ActionRow((workspace_action(), account_action())),
            ActionRow(secondary_actions),
            ActionRow((home_action(),)),
        ),
    )
