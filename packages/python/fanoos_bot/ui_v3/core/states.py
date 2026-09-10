from __future__ import annotations

from .actions import back_action, home_action, retry_action
from .contracts import Action, ActionRow, Context, EditPolicy, Screen, Section, Severity


def _tail_rows(*, retry: bool, back: bool, home: bool) -> tuple[ActionRow, ...]:
    result: list[ActionRow] = []
    if retry:
        result.append(ActionRow((retry_action(),)))
    nav: list[Action] = []
    if back:
        nav.append(back_action())
    if home:
        nav.append(home_action())
    if nav:
        result.append(ActionRow(tuple(nav)))
    return tuple(result)


def error_screen(
    message: str,
    *,
    title: str = "❌ مشکلی پیش آمد",
    identifier: str = "state.error",
    context: Context | None = None,
    retry: bool = True,
    back: bool = True,
    home: bool = True,
) -> Screen:
    return Screen(
        identifier=identifier,
        title=title,
        intro=message,
        severity=Severity.ERROR,
        context=context,
        action_rows=_tail_rows(retry=retry, back=back, home=home),
        edit_policy=EditPolicy.SEND_NEW,
    )


def empty_screen(
    title: str,
    message: str,
    *,
    identifier: str = "state.empty",
    next_actions: tuple[ActionRow, ...] = (),
    context: Context | None = None,
    back: bool = True,
    home: bool = True,
) -> Screen:
    return Screen(
        identifier=identifier,
        title=title,
        intro=message,
        severity=Severity.NEUTRAL,
        context=context,
        action_rows=next_actions + _tail_rows(retry=False, back=back, home=home),
    )


def success_screen(
    title: str,
    message: str,
    *,
    identifier: str = "state.success",
    next_actions: tuple[ActionRow, ...] = (),
    context: Context | None = None,
    home: bool = True,
) -> Screen:
    return Screen(
        identifier=identifier,
        title=title,
        intro=message,
        severity=Severity.SUCCESS,
        context=context,
        action_rows=next_actions + _tail_rows(retry=False, back=False, home=home),
    )


def unavailable_screen(
    *,
    message: str = "این بخش فعلاً در دسترس نیست. می‌توانید دوباره تلاش کنید یا به خانه برگردید.",
    context: Context | None = None,
) -> Screen:
    return Screen(
        identifier="state.unavailable",
        title="⚠️ دسترسی موقتاً ممکن نیست",
        intro=message,
        severity=Severity.WARNING,
        context=context,
        sections=(Section(body="اطلاعات قبلی را به‌عنوان وضعیت تازه در نظر نگیرید."),),
        action_rows=_tail_rows(retry=True, back=True, home=True),
        edit_policy=EditPolicy.SEND_NEW,
    )
