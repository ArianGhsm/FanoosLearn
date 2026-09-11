from __future__ import annotations

from dataclasses import dataclass

from .actions import (
    account_action,
    back_action,
    help_action,
    home_action,
    website_action,
    workspace_select_action,
)
from .contracts import ActionRow, Context, ListItem, Pagination, Screen, Section, Severity


@dataclass(frozen=True)
class WorkspaceOption:
    workspace_id: str
    label: str
    selected: bool = False
    detail: str = ""

    def __post_init__(self) -> None:
        if not self.workspace_id.strip() or not self.label.strip():
            raise ValueError("workspace option requires id and human label")


def workspace_list_screen(
    workspaces: tuple[WorkspaceOption, ...],
    *,
    pagination: Pagination | None = None,
) -> Screen:
    if not workspaces:
        raise ValueError("use no_workspace_screen for an empty workspace projection")
    visible = workspaces[:5]
    items = tuple(
        ListItem(
            workspace.label,
            workspace.detail,
            meta="فضای آموزشی فعال" if workspace.selected else "",
            marker="✓" if workspace.selected else "•",
        )
        for workspace in visible
    )
    action_rows = tuple(
        ActionRow((workspace_select_action(workspace.workspace_id, workspace.label),))
        for workspace in visible
        if not workspace.selected
    )
    return Screen(
        identifier="workspace.list",
        title="🏫 فضای آموزشی",
        intro="فضای فعال را ببینید یا یکی از فضاهای در دسترس را انتخاب کنید.",
        sections=(Section(items=items),),
        action_rows=action_rows + (ActionRow((back_action(), home_action())),),
        pagination=pagination,
    )


def no_workspace_screen(website_url: str) -> Screen:
    return Screen(
        identifier="workspace.empty",
        title="🏫 فضای آموزشی",
        intro="برای این حساب هنوز عضویت فعالی در یک فضای آموزشی ثبت نشده است.",
        severity=Severity.INFO,
        sections=(
            Section(
                body="فانوس فقط عضویت‌های ثبت‌شده در backend را نمایش می‌دهد و ربات فضای آموزشی جدیدی ایجاد نمی‌کند."
            ),
        ),
        action_rows=(ActionRow((account_action(), help_action())),)
        + ((ActionRow((website_action(website_url),)),) if website_url else ())
        + (ActionRow((home_action(),)),),
    )


def workspace_switch_success_screen(workspace_label: str) -> Screen:
    return Screen(
        identifier="workspace.switch_success",
        title="✅ فضای آموزشی تغییر کرد",
        intro="از این پس صفحه‌های بعدی با زمینه این فضای آموزشی باز می‌شوند.",
        severity=Severity.SUCCESS,
        context=Context("فضای آموزشی فعال", workspace_label),
        action_rows=(ActionRow((home_action(),)),),
    )
