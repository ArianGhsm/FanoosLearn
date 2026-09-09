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
    resources_action,
    schedule_action,
    website_action,
    workspace_action,
)
from .contracts import ActionRow, Context, ListItem, Screen, Section, Severity


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
        return ListItem(self.headline, self.detail, marker=marker)


def active_home_screen(
    workspace_label: str,
    *,
    date_label: str = "",
    next_schedule: HomeSlot | None = None,
    today_schedule: HomeSlot | None = None,
    latest_announcement: HomeSlot | None = None,
) -> Screen:
    """Build the bounded active-workspace home from canonical facts supplied by integration."""

    sections: list[Section] = []
    schedule_items = tuple(
        slot.list_item() for slot in (next_schedule, today_schedule) if slot is not None
    )
    if schedule_items:
        sections.append(Section(title="📅 برنامه", items=schedule_items[:2]))
    if latest_announcement is not None:
        sections.append(Section(title="📢 تازه", items=(latest_announcement.list_item(),)))
    if not sections:
        sections.append(
            Section(
                body="خلاصه امروز هنوز در این نما آماده نیست. از بخش‌های اصلی می‌توانید مستقیم ادامه دهید."
            )
        )

    return Screen(
        identifier="home.active",
        title="🏠 خانه",
        context=Context("فضای آموزشی", workspace_label, date_label),
        sections=tuple(sections[:3]),
        action_rows=(
            ActionRow((courses_action(), schedule_action())),
            ActionRow((grades_action(), notifications_action())),
            ActionRow((resources_action(), assessments_action())),
            ActionRow((account_action(), more_action())),
        ),
    )


def more_menu_screen(website_url: str) -> Screen:
    return Screen(
        identifier="core.more",
        title="بیشتر",
        intro="تنظیمات و مسیرهای تکمیلی فانوس.",
        action_rows=(
            ActionRow((workspace_action(), account_action())),
            ActionRow((help_action(), website_action(website_url))),
            ActionRow((home_action(),)),
        ),
    )
