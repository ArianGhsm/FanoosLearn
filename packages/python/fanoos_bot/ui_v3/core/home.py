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
    workspace_select_action,
)
from .contracts import ActionRow, Context, ListItem, Screen, Section
from .workspace import WorkspaceOption

_INTRO = "سرویس موردنظرت را از منوی زیر انتخاب کن."
_FOOTER = "اطلاعات شخصی فقط از حساب متصل و منبع رسمی نمایش داده می‌شود."
_NO_CLASS_LINE = "حساب شما متصل است ✅\nهنوز فضای آموزشی فعالی برای این حساب ندارید."
_NO_CLASS_BODY = "عضویت فضای آموزشی از اطلاعات رسمی فانوس می‌آید. این ربات فضای آموزشی تازه‌ای ایجاد نمی‌کند."
_PICK_CLASS_LINE = "یک فضای آموزشی را برای فعال‌سازی انتخاب کنید."
_PICK_CLASS_BODY = "انتخاب همیشه یک تصمیم صریح است؛ اولین فضای آموزشی به‌صورت خودکار فعال نمی‌شود."


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
    workspace_label: str | None,
    *,
    next_schedule: HomeSlot | None = None,
    latest_announcement: HomeSlot | None = None,
    workspace_options: tuple[WorkspaceOption, ...] = (),
    is_owner: bool = False,
    show_representative_requests: bool = False,
    notice: str = "",
) -> Screen:
    """The bot's one home screen and main menu -- for every viewer, not only
    one who already has a class selected.

    Provenance: this layout and wording is ported from the legacy Dent bot's
    live home screen (legacy/bot/dent_bot/bot_home_classops_ux_v2.py's
    canonical_home_screen -- not classops_ux_v3.py, which never held a home
    screen). "دنت‌یار | ورودی ۱۴۰۲" became "فانوس | {selected workspace}": the
    cohort year and the borrowed product name were the only hardcoded parts,
    everything else is reused verbatim.

    `workspace_label=None` covers both no-class-yet viewers (workspace_options
    empty) and members who have not chosen among their classes yet
    (workspace_options non-empty) -- neither renders the class-scoped rows
    (courses, schedule, grades, ...), since there is no class to scope them
    to; both still render the same screen identifier, title shape and footer,
    plus the join-wizard route and, for an owner, the management row --
    reaching class creation and representative appointment must never depend
    on already having a class. Selection is never invented here: the caller
    passes workspace_options only when the viewer has not chosen, and this
    function only ever offers a pick, never a default.

    Owner/representative rows are shown only when the backend already
    granted that capability -- the same probe-and-hide pattern
    application.py's more() uses, never a client-side authorization
    decision.
    """
    if workspace_label is None:
        if workspace_options:
            state_line, state_body = _PICK_CLASS_LINE, _PICK_CLASS_BODY
        else:
            state_line, state_body = _NO_CLASS_LINE, _NO_CLASS_BODY
        intro = f"{state_line}\n\n{_INTRO}"
        if notice:
            intro = f"✅ {notice}\n\n{intro}"
        sections = (Section(title="🏫 فضای آموزشی", body=state_body),)

        rows = [ActionRow((workspace_select_action(option.workspace_id, option.label),)) for option in workspace_options]
        if not workspace_options:
            rows.append(ActionRow((join_begin_action(),)))
        rows.append(ActionRow((workspace_action(), help_action())))
        rows.append(ActionRow((account_action(),)))
        if workspace_options:
            rows.append(ActionRow((join_begin_action(),)))
        if is_owner:
            rows.append(ActionRow((manage_action(),)))

        return Screen(
            identifier="home.active",
            title="فانوس",
            intro=intro,
            sections=sections,
            action_rows=tuple(rows),
            footer=_FOOTER,
        )

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
        title=f"فانوس | {workspace_label}",
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
