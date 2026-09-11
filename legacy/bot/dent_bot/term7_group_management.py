from __future__ import annotations

import html
from typing import Any, Callable

from . import class_operations as classops
from .app import DentBotApp
from .persian_datetime import to_persian_digits
from .site_api import SiteApiError
from .ui import Screen, button, frame, keyboard, native_rich_text

_INSTALLED = False


def _field_meta(field: str) -> tuple[str, range]:
    if field == "group10":
        return "صبح", range(1, 11)
    if field == "group8":
        return "عصر", range(11, 19)
    raise ValueError("invalid Term 7 group field")


def _student_number(row: dict[str, Any]) -> str:
    value = str(row.get("studentNumber") or "").strip()
    return value if value.isdigit() and len(value) <= 20 else ""


def _assignment(row: dict[str, Any]) -> dict[str, Any]:
    value = row.get("assignment")
    return dict(value) if isinstance(value, dict) else {}


def _assignment_summary(assignment: dict[str, Any]) -> str:
    parts: list[str] = []
    for field in ("group10", "group8"):
        label, _ = _field_meta(field)
        group = assignment.get(field)
        status = str(assignment.get(field + "StatusLabel") or "بدون گروه")
        if isinstance(group, int):
            parts.append(f"{label}: گروه {to_persian_digits(group)} · {status}")
        else:
            parts.append(f"{label}: بدون گروه")
    return "\n".join(parts)


def _field_summary(assignment: dict[str, Any], field: str) -> str:
    label, _ = _field_meta(field)
    group = assignment.get(field)
    status = str(assignment.get(field + "StatusLabel") or "بدون گروه")
    if isinstance(group, int):
        return f"{label}: گروه {to_persian_digits(group)} · {status}"
    return f"{label}: بدون گروه"


def _roster_summary(roster: list[dict[str, Any]]) -> str:
    morning_missing = 0
    afternoon_missing = 0
    for row in roster:
        assignment = _assignment(row)
        if not isinstance(assignment.get("group10"), int):
            morning_missing += 1
        if not isinstance(assignment.get("group8"), int):
            afternoon_missing += 1
    return (
        f"{to_persian_digits(len(roster))} دانشجو · "
        f"بدون گروه صبح: {to_persian_digits(morning_missing)} · "
        f"بدون گروه عصر: {to_persian_digits(afternoon_missing)}"
    )


def _home_screen(roster: list[dict[str, Any]]) -> Screen:
    return Screen(
        frame(
            "گروه‌بندی ترم ۷",
            "گروه صبح، گروه عصر و وضعیت سرگروهی هر دانشجو را از همین بخش مدیریت کن.",
            _roster_summary(roster),
        ),
        keyboard(
            [button("گروه‌های صبح", action="t7:g:10", style="primary")],
            [button("گروه‌های عصر", action="t7:g:8")],
            [button("↩️ مدیریت امور کلاس", action="class-operations:owner"), button("🏠 خانه", action="home")],
        ),
    )


def _group_index(roster: list[dict[str, Any]], field: str) -> Screen:
    label, groups = _field_meta(field)
    rows: list[tuple[int, int, str]] = []
    for group in groups:
        members = [row for row in roster if _assignment(row).get(field) == group]
        leader = next(
            (str(row.get("name") or "") for row in members if _assignment(row).get(field + "Status") == "leader"),
            "—",
        )
        rows.append((group, len(members), leader))

    fallback = [f"<b>گروه‌های {label}</b>", ""]
    rich = [
        f"<h2>گروه‌های {html.escape(label)}</h2>",
        "<table bordered striped compact><tr><th>گروه</th><th>تعداد</th><th>سرگروه</th></tr>",
    ]
    buttons: list[list[dict]] = []
    for group, count, leader in rows:
        fallback.append(
            f"گروه {to_persian_digits(group)} · {to_persian_digits(count)} نفر"
            + (f" · {html.escape(leader)}" if leader != "—" else "")
        )
        rich.append(
            f"<tr><td>{to_persian_digits(group)}</td><td>{to_persian_digits(count)}</td>"
            f"<td>{html.escape(leader)}</td></tr>"
        )
        buttons.append([
            button(
                f"گروه {to_persian_digits(group)} · {to_persian_digits(count)} نفر",
                action=f"t7:v:{10 if field == 'group10' else 8}:{group}",
            )
        ])
    rich.append("</table>")
    unassigned = sum(1 for row in roster if not isinstance(_assignment(row).get(field), int))
    if unassigned:
        fallback.extend(("", f"بدون گروه: {to_persian_digits(unassigned)} نفر"))
        rich.append(f"<footer>بدون گروه: {to_persian_digits(unassigned)} نفر</footer>")
        buttons.append([
            button(
                f"بدون گروه {label} · {to_persian_digits(unassigned)} نفر",
                action=f"t7:v:{10 if field == 'group10' else 8}:0",
            )
        ])
    buttons.append([button("↩️ گروه‌بندی ترم ۷", action="t7"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*buttons))


def _group_screen(roster: list[dict[str, Any]], field: str, group: int) -> Screen:
    label, _ = _field_meta(field)
    members = [
        row
        for row in roster
        if (_assignment(row).get(field) == group if group else not isinstance(_assignment(row).get(field), int))
    ]
    title = f"گروه {to_persian_digits(group)} {label}" if group else f"بدون گروه {label}"
    fallback = [f"<b>{html.escape(title)}</b>", ""]
    rich = [
        f"<h2>{html.escape(title)}</h2>",
        "<table bordered striped compact><tr><th>دانشجو</th><th>وضعیت</th></tr>",
    ]
    rows: list[list[dict]] = []
    if not members:
        fallback.append("دانشجویی در این بخش نیست.")
        rich.append("<tr><td colspan=\"2\">دانشجویی در این بخش نیست.</td></tr>")
    for row in members:
        name = str(row.get("name") or "دانشجو").strip() or "دانشجو"
        status = str(_assignment(row).get(field + "StatusLabel") or "بدون گروه")
        fallback.append(f"• <b>{html.escape(name)}</b> · {html.escape(status)}")
        rich.append(f"<tr><td>{html.escape(name)}</td><td>{html.escape(status)}</td></tr>")
        student_number = _student_number(row)
        if student_number:
            rows.append([button(name[:28], action=f"t7:s:{student_number}")])
    rich.append("</table>")
    rows.append([
        button(f"↩️ گروه‌های {label}", action=f"t7:g:{10 if field == 'group10' else 8}"),
        button("🏠 خانه", action="home"),
    ])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _student_screen(row: dict[str, Any]) -> Screen:
    name = str(row.get("name") or "دانشجو").strip() or "دانشجو"
    student_number = _student_number(row)
    assignment = _assignment(row)
    fallback = [f"<b>{html.escape(name)}</b>", "", _assignment_summary(assignment)]
    rich = [
        f"<h2>{html.escape(name)}</h2>",
        "<table bordered striped compact>",
        f"<tr><th>صبح</th><td>{html.escape(_field_summary(assignment, 'group10'))}</td></tr>",
        f"<tr><th>عصر</th><td>{html.escape(_field_summary(assignment, 'group8'))}</td></tr>",
        "</table>",
    ]
    rows: list[list[dict]] = [[
        button("تغییر گروه صبح", action=f"t7:c:10:{student_number}"),
        button("تغییر گروه عصر", action=f"t7:c:8:{student_number}"),
    ]]
    for field, short in (("group10", 10), ("group8", 8)):
        label, _ = _field_meta(field)
        if isinstance(assignment.get(field), int):
            leader = assignment.get(field + "Status") == "leader"
            rows.append([
                button(
                    f"{'برداشتن' if leader else 'تعیین'} سرگروهی {label}",
                    action=f"t7:l:{short}:{student_number}:{0 if leader else 1}",
                    style="danger" if leader else "success",
                )
            ])
    rows.append([button("↩️ گروه‌بندی ترم ۷", action="t7"), button("🏠 خانه", action="home")])
    return Screen(native_rich_text("\n".join(fallback), "".join(rich)), keyboard(*rows))


def _choose_group_screen(row: dict[str, Any], field: str) -> Screen:
    label, groups = _field_meta(field)
    name = str(row.get("name") or "دانشجو").strip() or "دانشجو"
    student_number = _student_number(row)
    current = _assignment(row).get(field)
    current_text = f"گروه {to_persian_digits(current)}" if isinstance(current, int) else "بدون گروه"
    rows: list[list[dict]] = []
    pair: list[dict] = []
    short = 10 if field == "group10" else 8
    for group in groups:
        pair.append(button(f"گروه {to_persian_digits(group)}", action=f"t7:u:{short}:{student_number}:{group}"))
        if len(pair) == 2:
            rows.append(pair)
            pair = []
    if pair:
        rows.append(pair)
    rows.append([button("پاک‌کردن گروه", action=f"t7:u:{short}:{student_number}:0", style="danger")])
    rows.append([button("↩️ بازگشت به دانشجو", action=f"t7:s:{student_number}")])
    return Screen(frame(f"تغییر گروه {label}", name, f"گروه فعلی: {current_text}"), keyboard(*rows))


def _find_student(roster: list[dict[str, Any]], student_number: str) -> dict[str, Any] | None:
    return next((row for row in roster if _student_number(row) == student_number), None)


def _strip_callback(value: object) -> str:
    raw = str(value or "").strip()
    return raw[3:] if raw.startswith("v1:") else raw


def _is_term7_callback(value: object) -> bool:
    action = _strip_callback(value)
    return action == "t7" or action.startswith("t7:")


def _roster(app: DentBotApp, user_id: int) -> list[dict[str, Any]]:
    response = app.site_api.request("academicTerm7Roster", user_id)
    return [row for row in response.get("roster", []) if isinstance(row, dict)]


def _owner_screen_with_term7(original: Callable[..., Screen], *args: Any, **kwargs: Any) -> Screen:
    screen = original(*args, **kwargs)
    rows = [list(row) for row in screen.keyboard.get("inline_keyboard", [])]
    if not any(
        str(item.get("callback_data") or "").endswith(":t7")
        for row in rows
        for item in row
        if isinstance(item, dict)
    ):
        insert_at = max(0, len(rows) - 1)
        rows.insert(insert_at, [button("گروه‌بندی ترم ۷", action="t7")])
    return Screen(screen.text, keyboard(*rows))


def _handle_term7(app: DentBotApp, callback: dict[str, Any]) -> None:
    message = dict(callback.get("message") or {})
    sender = dict(callback.get("from") or {})
    chat = dict(message.get("chat") or {})
    try:
        chat_id = int(chat.get("id") or sender.get("id") or 0)
        user_id = int(sender.get("id") or 0)
    except (TypeError, ValueError):
        return
    if chat_id == 0 or user_id == 0:
        return
    callback_id = str(callback.get("id") or "")
    if callback_id:
        try:
            app.api.answer_callback(callback_id)
        except Exception:
            pass

    def show(screen: Screen) -> None:
        classops._render_screen(app, chat_id, screen, callback=callback)

    blocked = classops._access_screen(app, user_id)
    if blocked is not None:
        show(blocked)
        return

    action = _strip_callback(callback.get("data"))
    try:
        roster = _roster(app, user_id)
        if action == "t7":
            show(_home_screen(roster))
            return
        if action in {"t7:g:10", "t7:g:8"}:
            show(_group_index(roster, "group10" if action.endswith(":10") else "group8"))
            return
        if action.startswith("t7:v:"):
            _, _, short, group_raw = action.split(":", 3)
            field = "group10" if short == "10" else "group8"
            show(_group_screen(roster, field, int(group_raw)))
            return
        if action.startswith("t7:s:"):
            student_number = action.rsplit(":", 1)[-1]
            row = _find_student(roster, student_number)
            show(
                _student_screen(row)
                if row
                else Screen(
                    frame("گروه‌بندی ترم ۷", "دانشجو پیدا نشد."),
                    keyboard([button("↩️ گروه‌بندی ترم ۷", action="t7")]),
                )
            )
            return
        if action.startswith("t7:c:"):
            _, _, short, student_number = action.split(":", 3)
            row = _find_student(roster, student_number)
            if row is None:
                show(
                    Screen(
                        frame("گروه‌بندی ترم ۷", "دانشجو پیدا نشد."),
                        keyboard([button("↩️ گروه‌بندی ترم ۷", action="t7")]),
                    )
                )
                return
            show(_choose_group_screen(row, "group10" if short == "10" else "group8"))
            return
        if action.startswith("t7:u:"):
            _, _, short, student_number, group_raw = action.split(":", 4)
            field = "group10" if short == "10" else "group8"
            group = int(group_raw)
            app.site_api.request(
                "academicTerm7AssignmentUpdate",
                user_id,
                studentNumber=student_number,
                field=field,
                group=None if group == 0 else group,
            )
            updated = _find_student(_roster(app, user_id), student_number)
            show(_student_screen(updated) if updated else _home_screen(_roster(app, user_id)))
            return
        if action.startswith("t7:l:"):
            _, _, short, student_number, leader_raw = action.split(":", 4)
            field = "group10" if short == "10" else "group8"
            app.site_api.request(
                "academicTerm7LeaderUpdate",
                user_id,
                studentNumber=student_number,
                field=field,
                leader=leader_raw == "1",
            )
            updated = _find_student(_roster(app, user_id), student_number)
            show(_student_screen(updated) if updated else _home_screen(_roster(app, user_id)))
            return
        show(_home_screen(roster))
    except (SiteApiError, ValueError):
        show(
            Screen(
                frame("گروه‌بندی ترم ۷", "این درخواست فعلاً کامل نشد.", "چند لحظه بعد دوباره امتحان کن."),
                keyboard([
                    button("↩️ مدیریت امور کلاس", action="class-operations:owner"),
                    button("🏠 خانه", action="home"),
                ]),
            )
        )


def install_term7_group_management() -> None:
    global _INSTALLED
    if _INSTALLED:
        return

    original_owner_screen = classops._owner_screen

    def owner_screen_wrapper(*args: Any, **kwargs: Any) -> Screen:
        return _owner_screen_with_term7(original_owner_screen, *args, **kwargs)

    classops._owner_screen = owner_screen_wrapper  # type: ignore[assignment]

    original_callback = DentBotApp._callback

    def callback_wrapper(
        self: DentBotApp,
        callback: dict[str, Any],
        *,
        interaction_version: int | None = None,
    ) -> None:
        if _is_term7_callback(callback.get("data")):
            _handle_term7(self, callback)
            return
        original_callback(self, callback, interaction_version=interaction_version)

    DentBotApp._callback = callback_wrapper  # type: ignore[method-assign]
    _INSTALLED = True
