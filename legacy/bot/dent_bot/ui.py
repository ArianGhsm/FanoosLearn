from __future__ import annotations

import html
import re
from dataclasses import dataclass
from urllib.parse import urlsplit

from .persian_datetime import format_jalali_datetime, to_persian_digits
from .payments import audience_label


@dataclass(frozen=True)
class Screen:
    text: str
    keyboard: dict


class NativeRichText(str):
    """A regular-message fallback carrying Telegram Rich Message HTML."""

    def __new__(cls, fallback: str, rich_html: str):
        value = super().__new__(cls, fallback)
        value.rich_html = rich_html
        return value


def native_rich_text(fallback: str, rich_html: str) -> NativeRichText:
    return NativeRichText(fallback, rich_html)


def button(
    text: str,
    *,
    action: str = "",
    url: str = "",
    style: str = "",
    switch_inline_query: str | None = None,
) -> dict:
    item: dict[str, str] = {"text": text}
    if action:
        callback_data = f"v1:{action}"
        if len(callback_data.encode("utf-8")) > 64:
            raise ValueError("Callback data exceeds the Telegram/Bale safe limit")
        item["callback_data"] = callback_data
    elif url:
        item["url"] = url
    elif switch_inline_query is not None:
        item["switch_inline_query"] = switch_inline_query
    else:
        raise ValueError("Button requires an action or URL")
    if style:
        item["style"] = style
    return item


def keyboard(*rows: list[dict]) -> dict:
    return {"inline_keyboard": list(rows)}


def required_channel_membership_screen(channel_username: str, *, check_unavailable: bool = False) -> Screen:
    username = channel_username.strip().lstrip("@")
    if re.fullmatch(r"[A-Za-z0-9_]{5,64}", username) is None:
        raise ValueError("Required Telegram channel username is invalid")
    status = (
        "بررسی عضویت فعلاً از تلگرام پاسخ نگرفت؛ برای حفظ دسترسی امن، ربات باز نشد."
        if check_unavailable
        else "هنوز عضویت این حساب در کانال تأیید نشده است."
    )
    return Screen(
        frame(
            "🔒 عضویت در کانال الزامی است",
            f"برای استفاده از هر بخش ربات، ابتدا عضو @{username} شو و سپس دکمهٔ بررسی عضویت را بزن.",
            status,
        ),
        keyboard(
            [button("📢 عضویت در کانال", url=f"https://t.me/{username}", style="primary")],
            [button("✅ بررسی عضویت", action="membership-check", style="success")],
        ),
    )


def bot_start_url(bot_username: str, start_parameter: str, *, platform: str) -> str:
    """Build an allowlisted bot deep link without accepting an arbitrary URL."""
    username = bot_username.strip().lstrip("@")
    if re.fullmatch(r"[A-Za-z0-9_]{4,64}", username) is None:
        return ""
    if re.fullmatch(r"[A-Za-z0-9_-]{1,64}", start_parameter) is None:
        return ""
    host = "ble.ir" if platform == "bale" else "t.me"
    return f"https://{host}/{username}?start={start_parameter}"


def frame(title: str, description: str, status: str = "") -> str:
    lines = [f"<b><u>{html.escape(title)}</u></b>", "", html.escape(description)]
    if status:
        lines.extend(("", f"<blockquote>{html.escape(status)}</blockquote>"))
    return "\n".join(lines)


def numbered_heading(index: int, title: object) -> str:
    return f"<b>🔹 {to_persian_digits(index)}. {html.escape(str(title))}</b>"


_EXAM_ACTION_REF = re.compile(r"[A-Za-z0-9_-]{1,20}")


def _safe_nonnegative_int(value: object) -> int:
    try:
        return max(0, int(value or 0))
    except (TypeError, ValueError, OverflowError):
        return 0


def _safe_exam_url(value: object) -> str:
    raw = str(value or "").strip()
    try:
        parsed = urlsplit(raw)
    except ValueError:
        return ""
    if (
        len(raw) > 1000
        or parsed.scheme != "https"
        or not parsed.netloc
        or parsed.username
        or parsed.password
    ):
        return ""
    return raw


def exam_screen(payload: dict, *, site_url: str, is_owner: bool) -> Screen:
    """Render the versioned, website-authoritative exam view on both adapters.

    The bot intentionally understands presentation fields and opaque actions only.
    Catalog, access, prices, enrolment, attempts, answers and scoring stay on the
    website. In an active assessment, feedback is suppressed defensively even if a
    malformed server response includes it.
    """
    view = dict(payload.get("view") or payload)
    kind = str(view.get("kind") or "hub")
    allowed_kinds = {
        "hub", "course", "enrollment", "payment", "mode", "question",
        "question-map", "review", "result", "history", "practice", "owner",
    }
    if kind not in allowed_kinds:
        kind = "hub"
    title = " ".join(str(view.get("title") or "آزمون‌ها").split())[:120]
    description = str(view.get("description") or "").strip()[:1800]
    status = str(view.get("statusText") or "").strip()[:400]
    lines = [f"<b>📝 {html.escape(title)}</b>"]
    if description:
        lines.extend(("", html.escape(description)))
    if status:
        lines.extend(("", f"<blockquote>{html.escape(status)}</blockquote>"))

    progress = dict(view.get("progress") or {})
    if progress:
        current = _safe_nonnegative_int(progress.get("current"))
        total = _safe_nonnegative_int(progress.get("total"))
        answered = _safe_nonnegative_int(progress.get("answered"))
        flagged = _safe_nonnegative_int(progress.get("flagged"))
        remaining = str(progress.get("remainingText") or "").strip()[:80]
        bits = []
        if total:
            bits.append(f"سؤال {to_persian_digits(current)} از {to_persian_digits(total)}")
        bits.append(f"پاسخ‌داده‌شده: {to_persian_digits(answered)}")
        if flagged:
            bits.append(f"نشان‌دار: {to_persian_digits(flagged)}")
        if remaining:
            bits.append(f"زمان: {html.escape(remaining)}")
        lines.extend(("", " · ".join(bits)))

    question = dict(view.get("question") or {})
    mode = str(view.get("mode") or "")
    attempt_status = str(view.get("attemptStatus") or "")
    if question:
        prompt = str(question.get("text") or "").strip()[:3200]
        if prompt:
            lines.extend(("", f"<b>{html.escape(prompt)}</b>"))
        media_url = _safe_exam_url(question.get("mediaUrl"))
        if media_url:
            lines.extend(("", f'<a href="{html.escape(media_url, quote=True)}">مشاهده تصویر یا فایل سؤال</a>'))
        note = str(question.get("note") or "").strip()[:700]
        if note:
            lines.extend(("", f"<blockquote>{html.escape(note)}</blockquote>"))
        feedback = dict(question.get("feedback") or {})
        may_reveal = mode != "assessment" or attempt_status in {"submitted", "expired"}
        if feedback and may_reveal:
            marker = "✅" if feedback.get("correct") is True else "❌" if feedback.get("correct") is False else "ℹ️"
            summary = str(feedback.get("summary") or "").strip()[:500]
            explanation = str(feedback.get("explanation") or "").strip()[:1400]
            if summary:
                lines.extend(("", f"<b>{marker} {html.escape(summary)}</b>"))
            if explanation:
                lines.extend(("", html.escape(explanation)))

    for section_item in [item for item in view.get("sections", []) if isinstance(item, dict)][:8]:
        section_title = " ".join(str(section_item.get("title") or "").split())[:100]
        body = str(section_item.get("body") or "").strip()[:1800]
        if section_title:
            lines.extend(("", f"<b>{html.escape(section_title)}</b>"))
        if body:
            lines.append(html.escape(body))

    actions = [item for item in view.get("actions", []) if isinstance(item, dict)][:18]
    grouped: dict[int, list[dict]] = {}
    for action in actions:
        label = " ".join(str(action.get("label") or "").split())[:40]
        if not label:
            continue
        style = str(action.get("style") or "")
        if style not in {"primary", "success", "danger"}:
            style = ""
        action_ref = str(action.get("ref") or "")
        url = _safe_exam_url(action.get("url"))
        item = None
        if _EXAM_ACTION_REF.fullmatch(action_ref):
            item = button(label, action=f"exam-action:{action_ref}", style=style)
        elif url:
            item = button(label, url=url, style=style)
        if item is None:
            continue
        try:
            row = min(17, max(0, int(action.get("row") or len(grouped))))
        except (TypeError, ValueError):
            row = len(grouped)
        grouped.setdefault(row, []).append(item)

    rows: list[list[dict]] = []
    for row in sorted(grouped):
        rows.extend([grouped[row][offset:offset + 2] for offset in range(0, len(grouped[row]), 2)])
    if is_owner and kind != "owner":
        rows.append([button("⚙️ مدیریت آزمون‌ها", action="exam-owner")])
    fallback = _safe_exam_url(view.get("siteUrl")) or f"{site_url}/exams/"
    rows.append([button("نسخه کامل سایت", url=fallback), button("🏠 منوی اصلی", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def notification_time(value: object, platform: str) -> str:
    del platform
    rendered = format_jalali_datetime(value)
    return html.escape(rendered) if rendered else ""


def notification_list_screen(payload: dict, refs: dict[str, str], *, platform: str, site_url: str) -> Screen:
    data = dict(payload.get("data") or {})
    summary = dict(data.get("summary") or {})
    items = [item for item in data.get("items", []) if isinstance(item, dict)]
    unread_count = max(0, int(summary.get("unreadCount") or 0))
    lines = [
        "<b>🔔 مرکز اعلان‌ها</b>",
        "",
        f"<blockquote>{to_persian_digits(unread_count)} خوانده‌نشده · "
        f"{to_persian_digits(len(items))} اعلان اخیر</blockquote>",
    ]
    rows: list[list[dict]] = []
    for item in items[:6]:
        notification_id = str(item.get("id") or "")
        ref = refs.get(notification_id, "")
        if not ref:
            continue
        title = " ".join(str(item.get("title") or "اعلان").split())
        marker = "🔵" if item.get("unread") else "✓"
        rows.append([button(f"{marker} {title[:32]}", action=f"notification:{ref}")])
    if not rows:
        lines.extend(("", "فعلاً اعلانی برای حساب شما نیست."))
    elif len(items) > 6:
        lines.extend(("", "۶ اعلان تازه‌تر اینجا نمایش داده می‌شود؛ آرشیو کامل در سایت است."))
    rows.append([
        button("↻ تازه‌سازی", action="notifications", style="primary"),
        button("⚙️ تنظیمات", url=f"{site_url}/account/#account-notifications"),
    ])
    rows.append([button("🏠 منوی اصلی", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def notification_detail_screen(
    item: dict,
    ref: str,
    *,
    platform: str,
    is_owner: bool,
    site_url: str = "",
    show_mark_read: bool = True,
) -> Screen:
    title = html.escape(str(item.get("title") or "اعلان"))
    body = html.escape(str(item.get("body") or ""))[:2800]
    date_text = notification_time(item.get("effectiveAt"), platform)
    lines = [f"<b>🔔 {title}</b>"]
    if date_text:
        lines.extend(("", f"<blockquote>{date_text}</blockquote>"))
    if body:
        lines.extend(("", body))
    rows: list[list[dict]] = []
    cta_url = str(item.get("ctaUrl") or item.get("ctaHref") or "")
    if cta_url.startswith("/") and site_url:
        cta_url = f"{site_url}{cta_url}"
    cta_label = str(item.get("ctaLabel") or "مشاهده جزئیات")
    if cta_url.startswith("https://") or cta_url == "http://foodstu.tums.ac.ir":
        rows.append([button(cta_label[:40], url=cta_url, style="primary")])
    for action in [value for value in item.get("actions", []) if isinstance(value, dict)][:4]:
        action_ref = str(action.get("ref") or "")
        label = " ".join(str(action.get("label") or "").split())[:40]
        style = str(action.get("style") or "")
        if not re.fullmatch(r"[A-Za-z0-9_-]{1,20}", action_ref) or not label:
            continue
        if style not in {"primary", "success", "danger"}:
            style = ""
        rows.append([button(label, action=f"notification-action:{ref}:{action_ref}", style=style)])
    status_row: list[dict] = []
    if show_mark_read:
        status_row.append(button("✓ مشاهده شد", action=f"notification-read:{ref}", style="success"))
    if is_owner:
        status_row.append(button("👁 آمار", action=f"notification-audience:{ref}"))
    if status_row:
        rows.append(status_row)
    rows.append([button("‹ اعلان‌ها", action="notifications"), button("🏠 خانه", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def notification_audience_screen(payload: dict, ref: str, *, platform: str) -> Screen:
    data = dict(payload.get("data") or {})
    record = dict(data.get("record") or {})
    summary = dict(data.get("summary") or {})
    recipient_count = max(0, int(summary.get("recipientCount") or 0))
    viewed_count = max(0, int(summary.get("viewedCount") or 0))
    pending_count = max(0, int(summary.get("pendingCount") or 0))
    lines = [
        f"<b>👁 آمار · {html.escape(str(record.get('title') or 'اعلان'))}</b>",
        "",
        f"<blockquote>مخاطبان: {to_persian_digits(recipient_count)} · "
        f"مشاهده‌شده: {to_persian_digits(viewed_count)} · "
        f"در انتظار: {to_persian_digits(pending_count)}</blockquote>",
    ]
    viewed = [item for item in data.get("viewed", []) if isinstance(item, dict)]
    if viewed:
        lines.extend(("", "<b>آخرین مشاهده‌ها</b>"))
        for entry in viewed[:8]:
            name = html.escape(str(entry.get("name") or "کاربر"))
            seen_at = notification_time(entry.get("readAt"), platform)
            lines.append(f"• {name} · {seen_at}" if seen_at else f"• {name}")
    if len(viewed) > 8:
        lines.append(f"و {to_persian_digits(len(viewed) - 8)} مورد دیگر")
    return Screen(
        "\n".join(lines),
        keyboard(
            [
                button("↻ تازه‌سازی", action=f"notification-audience:{ref}", style="primary"),
                button("‹ اعلان", action=f"notification:{ref}"),
            ],
            [button("🏠 منوی اصلی", action="home")],
        ),
    )


def notification_push_screen(item: dict, ref: str, *, platform: str) -> Screen:
    return notification_detail_screen(item, ref, platform=platform, is_owner=False)


def account_disconnect_notice_screen(disconnected_at: object, *, platform: str) -> Screen:
    platform_label = "تلگرام" if platform == "telegram" else "بله"
    when = format_jalali_datetime(disconnected_at)
    lines = [
        "<b>🔌 اتصال حساب قطع شد</b>",
        "",
        f"اتصال این حساب {html.escape(platform_label)} از داخل سایت قطع شد.",
        "",
        f"<blockquote>زمان قطع اتصال: <b>{html.escape(when)}</b></blockquote>",
        "",
        "اگر این کار را خودت انجام ندادی، وارد سایت شو و امنیت حساب و نشست‌های فعال را بررسی کن.",
    ]
    return Screen(
        "\n".join(lines),
        keyboard([button("🔗 اتصال دوباره", action="link-account", style="primary")]),
    )


def account_screen(
    site_url: str,
    *,
    platform: str,
    linked_user: dict | None = None,
    link_url: str = "",
    identity_state: dict | None = None,
    onboarding_profile: dict | None = None,
) -> Screen:
    home_row = [button("🏠 منوی اصلی", action="home")]
    platform_label = "بله" if platform == "bale" else "تلگرام"
    if linked_user:
        name = html.escape(str(linked_user.get("name") or "کاربر سایت"))
        role = html.escape(str(linked_user.get("roleLabel") or "حساب فعال"))
        dis_number = "".join(ch for ch in str(linked_user.get("disNumber") or "") if ch.isdigit())
        profile = dict(onboarding_profile or {})
        profile_lines = []
        if profile:
            full_name = html.escape(" ".join((str(profile.get("firstName") or "").strip(), str(profile.get("lastName") or "").strip())).strip())
            profile_lines = [
                f"نام و نام خانوادگی: <b>{full_name}</b>",
                f"رشته: {html.escape(str(profile.get('major') or 'ثبت نشده'))}",
                f"دانشگاه: {html.escape(str(profile.get('institution') or 'ثبت نشده'))}",
                f"استان: {html.escape(str(profile.get('province') or 'ثبت نشده'))}",
                f"ورودی: {html.escape(str(profile.get('entryYear') or 'ثبت نشده'))}",
                f"نوع پذیرش: {html.escape(str(profile.get('admissionType') or 'ثبت نشده'))}",
                f"شماره دانشجویی: <code>{html.escape(str(profile.get('studentNumber') or 'ثبت نشده'))}</code>",
                f"موبایل: <code>{html.escape(str(profile.get('phoneMasked') or 'ثبت نشده'))}</code>",
            ]
            if profile.get("isClassMember"):
                profile_lines.insert(0, "<b>✅ عضو تأییدشدهٔ ورودی ۱۴۰۲ دندانپزشکی تهران</b>")
            edit_status = {"pending": "در انتظار تأیید مالک", "approved": "تأیید و اعمال‌شده", "rejected": "ردشده"}.get(str(profile.get("editRequestStatus") or ""))
            if edit_status:
                profile_lines.append(f"آخرین درخواست ویرایش: <b>{edit_status}</b>")
        details = "\n".join(profile_lines)
        dis_lines = ["<b>🆔 کد DIS</b>"]
        if dis_number:
            dis_lines.extend([
                f"<code>{html.escape(dis_number)}</code>",
                "",
                "🔐 رمز پیش‌فرض شما پیش از تغییر رمز DIS: <code>111</code> (سه عدد یک)",
            ])
        else:
            dis_lines.append("ثبت نشده")
        dis_block = "\n".join(dis_lines)
        return Screen(
            f"<b>👤 حساب من</b>\n\n{name}\n<blockquote>{role} · متصل به {platform_label}</blockquote>"
            + (f"\n\n{details}\n\n<blockquote>مشخصات فقط خواندنی است؛ هر تغییر پس از تأیید مالک اعمال می‌شود.</blockquote>" if details else "")
            + f"\n\n{dis_block}",
            keyboard(
                [button("📊 مشاهده نمرات", action="grades", style="primary")],
                [button("✏️ می‌خواهید ویرایش کنید؟", action="profile-edit")],
                [button("مدیریت حساب سایت", url=f"{site_url}/account/")],
                home_row,
            ),
        )
    if link_url:
        return Screen(
            frame("👤 اتصال حساب", "در سایت وارد شو و اتصال این حساب ربات را تأیید کن.", "این لینک ۱۰ دقیقه اعتبار دارد و فقط یک‌بار قابل استفاده است."),
            keyboard(
                [button("اتصال امن در سایت", url=link_url, style="primary")],
                [button("بررسی اتصال", action="check-link")],
                home_row,
            ),
        )
    if onboarding_profile:
        profile = dict(onboarding_profile)
        full_name = html.escape(" ".join([
            str(profile.get("firstName") or "").strip(),
            str(profile.get("lastName") or "").strip(),
        ]).strip())
        major = html.escape(str(profile.get("major") or ""))
        institution = html.escape(str(profile.get("institution") or ""))
        entry_year = html.escape(str(profile.get("entryYear") or "ثبت نشده"))
        admission_type = html.escape(str(profile.get("admissionType") or ""))
        student_number = html.escape(str(profile.get("studentNumber") or "ثبت نشده"))
        phone = html.escape(str(profile.get("phoneMasked") or ""))
        return Screen(
            "<b>👤 مشخصات تأییدشده</b>\n\n"
            f"<b>{full_name}</b>\n{major} · {institution}\nورودی {entry_year}\n{admission_type}\n"
            f"شماره دانشجویی: <code>{student_number}</code>\nموبایل: <code>{phone}</code>\n\n"
            f"<blockquote>این پروفایل عمومی در {platform_label} ثبت است؛ برای اطلاعات خصوصی، حساب سایت را جداگانه متصل کن.</blockquote>",
            keyboard(
                [button("اتصال امن حساب سایت", action="link-account", style="primary")],
                home_row,
            ),
        )
    identity_state = dict(identity_state or {})
    return Screen(
        frame("🔒 احراز هویت لازم است", f"حساب {platform_label} هنوز به سایت متصل نیست.", "تأیید دستی غیرفعال است؛ از OTP سایت یا ورود امن سایت استفاده کن."),
        keyboard(
            [button("ورود با OTP سایت", action="class-auth", style="success")],
            [button("اتصال امن از سایت", action="link-account", style="primary")],
            [button("بررسی اتصال", action="check-link")],
        ),
    )


def profile_edit_fields_screen() -> Screen:
    rows = [
        [button("نام", action="profile-edit-field:firstName"), button("نام خانوادگی", action="profile-edit-field:lastName")],
        [button("رشته", action="profile-edit-field:major"), button("دانشگاه", action="profile-edit-field:institution")],
        [button("استان دانشگاه", action="profile-edit-field:province")],
        [button("سال ورود", action="profile-edit-field:entryYear")],
        [button("نیمسال و نوع پذیرش", action="profile-edit-field:admissionType")],
        [button("شماره دانشجویی", action="profile-edit-field:studentNumber")],
        [button("انصراف", action="account", style="danger")],
    ]
    return Screen(
        "<b>✏️ درخواست ویرایش مشخصات</b>\n\nبخشی را انتخاب کن. مقدار فعلی تا زمان تأیید مالک هیچ تغییری نمی‌کند.",
        keyboard(*rows),
    )


def profile_edit_prompt_screen(field_label: str) -> Screen:
    return Screen(
        f"<b>ویرایش {html.escape(field_label)}</b>\n\nمقدار پیشنهادی را بفرست. درخواست برای مالک ارسال می‌شود و فقط پس از تأیید او اعمال خواهد شد.",
        keyboard([button("انصراف", action="profile-edit-cancel", style="danger")]),
    )


def profile_edit_requests_screen(payload: dict) -> Screen:
    items = [item for item in payload.get("requests", []) if isinstance(item, dict)]
    lines = ["<b>✏️ درخواست‌های ویرایش مشخصات</b>", "", f"در انتظار: <b>{len(items)}</b>"]
    rows = []
    for index, item in enumerate(items[:30], 1):
        ref = str(item.get("ref") or "")
        lines.extend((
            "",
            f"<b>{index}. {html.escape(str(item.get('name') or 'کاربر'))}</b>",
            f"{html.escape(str(item.get('fieldLabel') or 'بخش'))}",
            f"فعلی: <code>{html.escape(str(item.get('previousValue') or 'ثبت نشده'))}</code>",
            f"پیشنهادی: <code>{html.escape(str(item.get('value') or ''))}</code>",
            f"شماره دانشجویی: <code>{html.escape(str(item.get('studentNumber') or ''))}</code>",
        ))
        if ref:
            rows.append([
                button("تأیید", action=f"profile-edit-approve:{ref}", style="success"),
                button("رد", action=f"profile-edit-reject:{ref}", style="danger"),
            ])
    if not items:
        lines.extend(("", "درخواستی وجود ندارد."))
    rows.extend(([button("تازه‌سازی", action="profile-edit-requests")], [button("🏠 منوی اصلی", action="home")]))
    return Screen("\n".join(lines), keyboard(*rows))


def owner_identity_mappings_screen(payload: dict, *, platform: str) -> Screen:
    platform_label = "بله" if platform == "bale" else "تلگرام"
    mappings = [item for item in payload.get("mappings", []) if isinstance(item, dict)]
    lines = [
        f"<b>🔗 اتصال حساب‌های {platform_label}</b>",
        "",
        f"اتصال‌های قطعی ثبت‌شده: <b>{len(mappings)}</b>",
        "<blockquote>اتصال تازه یا جایگزین فقط با OTP سایت یا ورود امن همان دانشجو ساخته می‌شود؛ تأیید و اتصال دستی غیرفعال است.</blockquote>",
    ]
    rows: list[list[dict]] = []
    for index, item in enumerate(mappings[:25], 1):
        ref = str(item.get("ref") or "")
        name = html.escape(str(item.get("name") or "کاربر سایت"))
        student_number = html.escape(str(item.get("studentNumber") or ""))
        platform_user_id = html.escape(str(item.get("platformUserId") or ""))
        linked_at = notification_time(item.get("linkedAt"), platform)
        source = html.escape(str(item.get("source") or "نامشخص"))
        lines.extend((
            "",
            f"<b>{index}. {name}</b>",
            f"دانشجو: <code>{student_number}</code>",
            f"{platform_label}: <code>{platform_user_id}</code>",
            f"منبع: {source}" + (f" · {linked_at}" if linked_at else ""),
        ))
        if ref:
            rows.append([button(f"حذف اتصال {name[:18]}", action=f"identity-mapping-remove:{ref}", style="danger")])
    if not mappings:
        lines.extend(("", f"هنوز اتصال {platform_label} ثبت نشده است."))
    rows.extend((
        [button("تازه‌سازی", action="identity-mappings", style="primary")],
        [button("بازگشت به مدیریت", action="admin"), button("🏠 منوی اصلی", action="home")],
    ))
    return Screen("\n".join(lines), keyboard(*rows))


def identity_mapping_remove_screen(item: dict, *, platform: str = "telegram") -> Screen:
    platform_label = "بله" if platform == "bale" else "تلگرام"
    return Screen(
        f"<b>⚠️ حذف اتصال {platform_label}</b>\n\n"
        f"{html.escape(str(item.get('name') or 'کاربر سایت'))}\n"
        f"<code>{html.escape(str(item.get('studentNumber') or ''))}</code> · "
        f"<code>{html.escape(str(item.get('platformUserId') or ''))}</code>\n\n"
        "برای ادامه، دلیل حذف را در یک پیام بنویس.",
        keyboard([button("انصراف", action="identity-mapping-remove-cancel", style="danger")]),
    )


def identity_mapping_remove_confirmation(payload: dict, *, platform: str = "telegram") -> Screen:
    platform_label = "بله" if platform == "bale" else "تلگرام"
    item = dict(payload.get("item") or {})
    return Screen(
        f"<b>⚠️ تأیید نهایی حذف اتصال {platform_label}</b>\n\n"
        f"{html.escape(str(item.get('name') or 'کاربر سایت'))}\n"
        f"<code>{html.escape(str(item.get('studentNumber') or ''))}</code> · "
        f"<code>{html.escape(str(item.get('platformUserId') or ''))}</code>\n"
        f"دلیل: {html.escape(str(payload.get('reason') or ''))}",
        keyboard(
            [button("حذف اتصال", action="identity-mapping-remove-confirm", style="danger")],
            [button("انصراف", action="identity-mapping-remove-cancel")],
        ),
    )


def grades_screen(site_url: str, payload: dict, *, platform: str = "telegram") -> Screen:
    grades = [item for item in payload.get("grades", []) if str(item.get("value", "")).strip()]
    stats = {
        str(item.get("label") or ""): item
        for item in payload.get("stats", [])
        if isinstance(item, dict)
    }
    name = html.escape(str(payload.get("name") or "دانشجو"))
    rich_url = f"{site_url}/grades/"
    lines = [
        "<b><u>📊 کارنامه من</u></b>",
        "",
        name,
        f"<blockquote>تعداد نمره‌های ثبت‌شده: <b>{to_persian_digits(len(grades))}</b></blockquote>",
    ]
    if grades:
        omitted = 0
        for index, item in enumerate(grades[:30], 1):
            raw_label = " ".join(str(item.get("label") or "درس").split())
            label = raw_label[:72] + ("…" if len(raw_label) > 72 else "")
            value = to_persian_digits(item.get("value") or "—")
            maximum = item.get("maxScore")
            score = f"{value} از {to_persian_digits(maximum)}" if maximum not in (None, "") else value
            stat = stats.get(str(item.get("label") or ""), {})
            average = stat.get("classAverage")
            average_text = f"{float(average):.2f}" if average is not None else "—"
            rank = stat.get("rank")
            total = stat.get("totalWithScore")
            rank_text = f"{int(rank)} از {int(total)}" if rank is not None and total else "—"
            card = (
                f"{numbered_heading(index, label)}\n"
                f"<blockquote>🎯 نمره: <code>{html.escape(score)}</code>\n"
                f"📈 میانگین کلاس: <b>{html.escape(to_persian_digits(average_text))}</b>\n"
                f"🏅 رتبه: <b>{html.escape(to_persian_digits(rank_text))}</b></blockquote>"
            )
            if len("\n".join(lines)) + len(card) + 2 > 3500:
                omitted = len(grades) - index + 1
                break
            lines.extend(("", card))
        if omitted:
            lines.extend(("", f"<blockquote>و {to_persian_digits(omitted)} نمرهٔ دیگر در نمای کامل.</blockquote>"))
    else:
        lines.extend(("", "هنوز نمره‌ای برای حساب شما ثبت نشده است."))
    lines.extend(("", "<blockquote>منبع این اطلاعات کارنامه سایت است و با هر تازه‌سازی دوباره خوانده می‌شود.</blockquote>"))

    rich_parts = [
        "<h2>📊 کارنامه من</h2>",
        f"<p><b>{name}</b><br/>تعداد نمره‌های ثبت‌شده: <b>{to_persian_digits(len(grades))}</b></p>",
    ]
    if grades:
        rich_parts.append(
            "<table bordered striped compact><caption>جدول نمره‌ها</caption>"
            "<tr><th>درس</th><th>نمره</th><th>میانگین</th><th>رتبه</th></tr>"
        )
        for item in grades[:30]:
            label = html.escape(" ".join(str(item.get("label") or "درس").split())[:96])
            value = html.escape(to_persian_digits(item.get("value") or "—"))
            maximum = item.get("maxScore")
            score = f"{value} از {html.escape(to_persian_digits(maximum))}" if maximum not in (None, "") else value
            stat = stats.get(str(item.get("label") or ""), {})
            average = stat.get("classAverage")
            average_text = to_persian_digits(f"{float(average):.2f}") if average is not None else "—"
            rank = stat.get("rank")
            total = stat.get("totalWithScore")
            rank_text = to_persian_digits(f"{int(rank)} از {int(total)}") if rank is not None and total else "—"
            rich_parts.append(
                f"<tr><td>{label}</td><td><b>{score}</b></td>"
                f"<td>{html.escape(average_text)}</td><td>{html.escape(rank_text)}</td></tr>"
            )
        rich_parts.append("</table>")
    else:
        rich_parts.append("<p>هنوز نمره‌ای برای حساب شما ثبت نشده است.</p>")
    rich_parts.append("<footer>منبع: کارنامهٔ سایت؛ هر تازه‌سازی مستقیماً از منبع اصلی خوانده می‌شود.</footer>")
    return Screen(
        native_rich_text("\n".join(lines), "".join(rich_parts)),
        keyboard(
            [button("↻ تازه‌سازی", action="grades", style="primary"), button("کارنامه سایت", url=rich_url)],
            [button("🏠 منوی اصلی", action="home")],
        ),
    )


def navid_screen(payload: dict, *, platform: str, site_url: str) -> Screen:
    status = dict(payload.get("status") or {})
    state = dict(status.get("state") or {})
    counts = dict(status.get("snapshotCounts") or {})
    automation = dict(payload.get("automation") or {})
    assignments = [item for item in payload.get("assignments", []) if isinstance(item, dict)]
    action = str(state.get("actionRequired") or "")
    action_labels = {
        "": ("🟢", "آماده"),
        "none": ("🟢", "آماده"),
        "save-credentials": ("🔴", "اطلاعات ورود ثبت نشده"),
        "update-credentials": ("🔴", "اطلاعات ورود نیاز به اصلاح دارد"),
        "reconnect": ("🟠", "اتصال نوید نیاز به بازسازی دارد"),
        "captcha": ("🟡", "در انتظار کپچا"),
    }
    marker, label = action_labels.get(action, ("🟡", html.escape(action or "نیازمند بررسی")))
    last_success = notification_time(state.get("lastSuccessAt"), platform)
    challenge_expiry = notification_time(state.get("challengeExpiresAt"), platform)
    completed_at = notification_time(automation.get("completedAt"), platform)
    lines = [
        "<b><u>🎓 مرکز نوید</u></b>",
        "",
        f"وضعیت اتصال: <b>{marker} {label}</b>",
        f"درس‌های همگام‌شده: <b>{max(0, int(counts.get('courses') or 0))}</b>",
        f"تکلیف‌های فعلی: <b>{max(0, int(counts.get('assignments') or 0))}</b>",
    ]
    if last_success:
        lines.append(f"آخرین بررسی موفق: {last_success}")
    if completed_at:
        lines.append(f"بررسی روزانه تکمیل‌شده: {completed_at}")
    if state.get("hasActiveChallenge"):
        challenge_line = "کپچای فعال در انتظار پاسخ است."
        if challenge_expiry:
            challenge_line += f" اعتبار تا {challenge_expiry}"
        lines.extend(("", f"<blockquote>{challenge_line}</blockquote>"))
    last_error = html.escape(str(state.get("lastError") or ""))[:400]
    if last_error:
        lines.extend(("", f"<blockquote expandable><b>آخرین خطای همگام‌سازی</b>\n{last_error}</blockquote>"))
    if assignments:
        for index, item in enumerate(assignments[:8], 1):
            title = " ".join(str(item.get("title") or "تکلیف").split())[:96]
            course = " ".join(str(item.get("courseTitle") or "درس").split())[:60]
            due = format_jalali_datetime(item.get("endDateIso")) or to_persian_digits(item.get("endDateShamsi") or "بدون مهلت")
            lines.extend((
                "",
                numbered_heading(index, title),
                f"<blockquote>📚 درس: <b>{html.escape(course)}</b>\n⏳ مهلت: {html.escape(due)}</blockquote>",
            ))
        nearest = assignments[0]
        nearest_title = html.escape(" ".join(str(nearest.get("title") or "تکلیف").split())[:160])
        nearest_description = html.escape(" ".join(str(nearest.get("description") or "").split())[:500])
        nearest_course = html.escape(" ".join(str(nearest.get("courseTitle") or "درس").split())[:80])
        nearest_due = notification_time(nearest.get("endDateIso"), platform) or html.escape(
            to_persian_digits(nearest.get("endDateShamsi") or "بدون مهلت")
        )
        detail = f"<b>{nearest_title}</b>\nدرس: {nearest_course}\nمهلت: {nearest_due}"
        if nearest_description:
            detail += f"\n\n{nearest_description}"
        lines.extend(("", f"<blockquote expandable><b>جزئیات نزدیک‌ترین تکلیف</b>\n{detail}</blockquote>"))
    else:
        lines.extend(("", "تکلیفی در snapshot فعلی نوید وجود ندارد."))

    rich_parts = [
        "<h2>🎓 مرکز نوید</h2>",
        f"<p>وضعیت اتصال: <b>{marker} {label}</b><br/>"
        f"درس‌های همگام‌شده: <b>{to_persian_digits(max(0, int(counts.get('courses') or 0)))}</b><br/>"
        f"تکلیف‌های فعلی: <b>{to_persian_digits(max(0, int(counts.get('assignments') or 0)))}</b></p>",
    ]
    if last_success:
        rich_parts.append(f"<p>آخرین بررسی موفق: {html.escape(last_success)}</p>")
    if state.get("hasActiveChallenge"):
        rich_parts.append(f"<blockquote>{html.escape(challenge_line)}</blockquote>")
    if last_error:
        rich_parts.append(f"<details><summary>آخرین خطای همگام‌سازی</summary><p>{last_error}</p></details>")
    if assignments:
        rich_parts.append(
            "<table bordered striped compact><caption>تکلیف‌های نزدیک</caption>"
            "<tr><th>تکلیف</th><th>درس</th><th>مهلت</th></tr>"
        )
        for item in assignments[:12]:
            title = html.escape(" ".join(str(item.get("title") or "تکلیف").split())[:120])
            course = html.escape(" ".join(str(item.get("courseTitle") or "درس").split())[:80])
            due = html.escape(
                format_jalali_datetime(item.get("endDateIso"))
                or to_persian_digits(item.get("endDateShamsi") or "بدون مهلت")
            )
            rich_parts.append(f"<tr><td><b>{title}</b></td><td>{course}</td><td>{due}</td></tr>")
        rich_parts.append("</table>")
        if nearest_description:
            rich_parts.append(
                f"<details><summary>جزئیات نزدیک‌ترین تکلیف</summary>"
                f"<p><b>{nearest_title}</b><br/>{nearest_description}</p></details>"
            )
    else:
        rich_parts.append("<p>تکلیفی در snapshot فعلی نوید وجود ندارد.</p>")
    return Screen(
        native_rich_text("\n".join(lines), "".join(rich_parts)),
        keyboard(
            [button("بررسی امروز / دریافت کپچا", action="navid-check", style="success")],
            [button("تازه‌سازی وضعیت", action="navid", style="primary")],
            [button("پنل نوید سایت", url=f"{site_url}/admin/#navid")],
            [button("بازگشت به مدیریت", action="admin"), button("🏠 منوی اصلی", action="home")],
        ),
    )


def navid_assignment_photo_caption(item: dict, *, event_type: str, platform: str) -> str:
    event_labels = {
        "new": "تکلیف جدید",
        "week": "یک هفته تا پایان مهلت",
        "day": "یک روز تا پایان مهلت",
        "test": "پیش‌نمایش مالک",
    }
    title = html.escape(" ".join(str(item.get("title") or "تکلیف نوید").split())[:180])
    course = html.escape(" ".join(str(item.get("courseTitle") or "درس").split())[:90])
    description = html.escape(" ".join(str(item.get("description") or "").split())[:700])
    created = notification_time(item.get("createdDateIso"), platform) or html.escape(
        to_persian_digits(item.get("createdDateShamsi") or "—")
    )
    due = notification_time(item.get("endDateIso"), platform) or html.escape(
        to_persian_digits(item.get("endDateShamsi") or "—")
    )
    event = event_labels.get(event_type, "یادآوری تکلیف")
    lines = [
        "<b><u>🎓 یادآوری تکلیف نوید</u></b>",
        "",
        f"<blockquote><b>{html.escape(event)}</b>\n{title}</blockquote>",
        "",
        f"📚 <b>درس:</b> {course}",
        f"🆕 <b>ایجاد:</b> {created}",
        f"⏳ <b>مهلت:</b> {due}",
    ]
    if description:
        lines.extend(("", description))
    lines.extend(("", "<i>منبع: آخرین snapshot تأییدشدهٔ نوید در سایت</i>"))
    return "\n".join(lines)


def owner_grade_screen(site_url: str) -> Screen:
    return Screen(
        "<b>📊 ثبت نمره</b>\n\nبرای ثبت یا ویرایش، پیام را با این قالب بفرست:\n"
        "<code>/setgrade شماره‌دانشجویی | نام درس | سقف | نمره</code>\n\n"
        "<blockquote>نمونه: /setgrade 402… | اندو نظری ۱ | ۲۰ | ۱۸.۵</blockquote>",
        keyboard([button("مدیریت کامل در سایت", url=f"{site_url}/account/")], [button("🏠 منوی اصلی", action="home")]),
    )


def format_rials(value: object) -> str:
    try:
        rials = max(0, int(value))
    except (TypeError, ValueError):
        rials = 0
    return f"{rials // 10:,} تومان"


def term_subscription_screen(policy: dict, decision: dict, *, term: int = 7) -> Screen:
    period = decision.get("period")
    price = html.escape(format_rials(policy.get("monthlyPriceRials")))
    mode = str(decision.get("accessPath") or "none")
    effective = str(decision.get("reason") or "") != "open-policy"
    rows: list[list[dict]] = []
    if period is not None:
        period_label = to_persian_digits(period.month_label)
        end_label = to_persian_digits(period.end_label)
    else:
        period_label = "—"
        end_label = "—"
    if not effective:
        status = "🟢 دسترسی فعلی طبق سیاست باز فعال است"
    elif mode == "both":
        status = "✅ اشتراک پرداختی و 🎁 دسترسی رایگان هر دو فعال‌اند"
    elif mode == "paid":
        status = "✅ اشتراک این ماه پرداخت شده است"
    elif mode == "complimentary":
        status = "🎁 دسترسی رایگان مالک فعال است"
    else:
        status = "🔒 برای این ماه دسترسی فعالی ثبت نشده است"
        rows.append([button("💳 خرید اشتراک", action=f"term-subscription-buy:{term}", style="success")])
    rows.extend((
        [button("ℹ️ توضیحات", action=f"term-subscription-info:{term}")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    start = to_persian_digits(str(policy.get("activeFromJalali") or "").replace("-", "/"))
    latest = dict(decision.get("latestPayment") or {})
    latest_line = ""
    if latest:
        paid_at = format_jalali_datetime(latest.get("paidAt")) or "—"
        latest_period = to_persian_digits(str(latest.get("billingPeriod") or "").replace("term", "ترم "))
        latest_line = f"<b>آخرین پرداخت:</b> {html.escape(paid_at)} · <code>{html.escape(latest_period)}</code>\n"
    text = (
        f"<b>🔒 دسترسی جزوات ترم {to_persian_digits(term)}</b>\n\n"
        f"{status}\n\n"
        f"<b>دوره:</b> {html.escape(period_label)}\n"
        f"<b>اعتبار پرداخت:</b> تا پایان {html.escape(end_label)}\n"
        f"<b>هزینهٔ کامل ماه:</b> <code>{to_persian_digits(price)}</code>\n"
        f"{latest_line}"
        f"<blockquote>شروع سیاست: {start} · خرید وسط ماه با مبلغ کامل فقط تا پایان همان ماه شمسی معتبر است.</blockquote>"
    )
    return Screen(text, keyboard(*rows))


def term_subscription_info_screen(policy: dict, *, term: int = 7) -> Screen:
    return Screen(
        f"<b>ℹ️ اشتراک جزوات ترم {to_persian_digits(term)}</b>\n\n"
        "اشتراک rolling سی‌روزه نیست؛ هر پرداخت فقط ماه شمسی جاری را پوشش می‌دهد. "
        "خرید در میانهٔ ماه تخفیف یا انتقال اعتبار به ماه بعد ندارد.\n\n"
        "پس از تأیید واقعی درگاه، دسترسی همان لحظه فعال می‌شود. تمدید خودکار یا برداشت خودکار انجام نمی‌شود.\n\n"
        "<blockquote>همهٔ فایل‌ها حتی برای دسترسی رایگان، فقط به‌صورت نسخهٔ شخصی‌سازی‌شده، واترمارک‌دار و محافظت‌شده ارسال می‌شوند.</blockquote>",
        keyboard(
            [button("مشاهده وضعیت", action=f"term-subscription:{term}")],
            [button("🏠 منوی اصلی", action="home")],
        ),
    )


def term_subscription_admin_screen(policy: dict, report: dict) -> Screen:
    term = int(policy.get("term") or 7)
    period = report.get("period")
    period_label = to_persian_digits(period.month_label) if period is not None else "—"
    enabled = bool(policy.get("enabled")) and str(policy.get("mode")) == "subscription"
    renewal = bool(policy.get("renewalRemindersEnabled"))
    renewal_rate = f"{float(report.get('renewalRate') or 0.0) * 100:.1f}%"
    no_access_line = (
        f"❌ بدون اشتراک/رایگان: <b>{to_persian_digits(report.get('withoutSubscription', 0))}</b>\n"
        if report.get("directoryCount") is not None else ""
    )
    return Screen(
        f"<b>📚 اشتراک جزوات ترم {to_persian_digits(term)}</b>\n\n"
        f"وضعیت سیاست: <b>{'فعال' if enabled else 'باز/غیرفعال'}</b>\n"
        f"دورهٔ فعلی: <b>{html.escape(period_label)}</b>\n"
        f"مبلغ: <code>{to_persian_digits(format_rials(policy.get('monthlyPriceRials')))}</code>\n\n"
        f"👥 مشترکین پرداختی: <b>{to_persian_digits(report.get('paidSubscribers', 0))}</b>\n"
        f"🎁 دسترسی رایگان: <b>{to_persian_digits(report.get('complimentary', 0))}</b>\n"
        f"🔓 کل دسترسی فعال: <b>{to_persian_digits(report.get('totalActiveAccess', 0))}</b>\n"
        + no_access_line
        +
        f"💳 درآمد دوره: <code>{to_persian_digits(format_rials(report.get('revenueRials', 0)))}</code>\n"
        f"⌛ پرداخت‌نکرده از ماه قبل: <b>{to_persian_digits(report.get('unpaidPreviousSubscribers', 0))}</b>\n"
        f"🔁 نرخ تمدید: <b>{to_persian_digits(renewal_rate)}</b>\n"
        f"🆕 مشترک جدید: <b>{to_persian_digits(report.get('newSubscribers', 0))}</b>\n"
        f"🔔 یادآوری تمدید: <b>{'فعال' if renewal else 'خاموش'}</b>",
        keyboard(
            [button("💰 مبلغ ماهانه", action=f"term-subscription-price:{term}"), button("⚙️ تنظیمات", action=f"term-subscription-settings:{term}")],
            [button("🎁 اعطای رایگان", action=f"term-subscription-grant:{term}", style="success"), button("📋 دسترسی‌های رایگان", action=f"term-subscription-free:{term}")],
            [button("🔎 جستجوی دانشجو", action=f"term-subscription-grant:{term}"), button("📊 آمار", action=f"term-subscription-admin:{term}")],
            [button("📤 CSV", action=f"term-subscription-export:{term}:csv"), button("📝 TXT", action=f"term-subscription-export:{term}:txt")],
            [button("↻ تازه‌سازی", action=f"term-subscription-admin:{term}"), button("مرکز پرداخت‌ها", action="admin-payments")],
        ),
    )


def term_subscription_settings_screen(policy: dict) -> Screen:
    term = int(policy.get("term") or 7)
    enabled = bool(policy.get("enabled"))
    mode = str(policy.get("mode") or "open")
    reminders = bool(policy.get("renewalRemindersEnabled"))
    return Screen(
        f"<b>⚙️ تنظیمات اشتراک ترم {to_persian_digits(term)}</b>\n\n"
        f"مدل دسترسی: <b>{'اشتراک ماهانه' if mode == 'subscription' else 'باز'}</b>\n"
        f"اعمال سیاست: <b>{'فعال' if enabled else 'غیرفعال'}</b>\n"
        f"شروع: <code>{to_persian_digits(str(policy.get('activeFromJalali') or '').replace('-', '/'))}</code>\n"
        f"یادآوری تمدید: <b>{'فعال' if reminders else 'خاموش'}</b>\n\n"
        "<blockquote>غیرفعال‌کردن سیاست یا تغییر مدل به «باز» فروش اشتراک را متوقف می‌کند و رفتار دسترسی را باز می‌گذارد؛ entitlementهای قبلی حذف نمی‌شوند.</blockquote>",
        keyboard(
            [button("فعال/غیرفعال", action=f"term-subscription-toggle:{term}")],
            [button("تغییر مدل باز/اشتراک", action=f"term-subscription-mode:{term}")],
            [button("روشن/خاموش یادآوری", action=f"term-subscription-reminders:{term}")],
            [button("💰 تغییر مبلغ", action=f"term-subscription-price:{term}")],
            [button("📅 تغییر تاریخ شروع", action=f"term-subscription-start:{term}")],
            [button("📚 سیاست ترم‌ها", action="term-access-policies")],
            [button("بازگشت", action=f"term-subscription-admin:{term}")],
        ),
    )


def term_access_policies_screen(policies: list[dict]) -> Screen:
    lines = ["<b>📚 سیاست دسترسی ترم‌ها</b>", "", "هر ترم یک policy مستقل و قابل تغییر دارد."]
    rows: list[list[dict]] = []
    for policy in policies:
        term = int(policy.get("term") or 0)
        mode = "اشتراک" if str(policy.get("mode")) == "subscription" and policy.get("enabled") else "باز"
        lines.append(
            f"• ترم <b>{to_persian_digits(term)}</b> · {mode} · "
            f"<code>{to_persian_digits(format_rials(policy.get('monthlyPriceRials')))}</code>"
        )
        rows.append([button(f"مدیریت ترم {to_persian_digits(term)}", action=f"term-subscription-admin:{term}")])
    rows.extend((
        [button("➕ افزودن policy ترم", action="term-access-policy-add", style="success")],
        [button("مرکز پرداخت‌ها", action="admin-payments")],
    ))
    return Screen("\n".join(lines), keyboard(*rows))


def complimentary_access_list_screen(items: list[dict], *, term: int = 7) -> Screen:
    lines = [f"<b>🎁 دسترسی‌های رایگان ترم {to_persian_digits(term)}</b>", ""]
    rows: list[list[dict]] = []
    for index, item in enumerate(items[:40], 1):
        name = html.escape(str(item.get("displayName") or "دانشجو"))
        student = html.escape(to_persian_digits(item.get("studentNumber") or "—"))
        lines.append(f"{to_persian_digits(index)}. <b>{name}</b> · <code>{student}</code>")
        rows.append([button(f"لغو · {str(item.get('displayName') or item.get('studentNumber') or '')[:25]}", action=f"term-subscription-revoke:{int(item.get('id') or 0)}")])
    if not items:
        lines.append("هنوز هیچ دسترسی رایگانی ثبت نشده است.")
    rows.extend((
        [button("🎁 اعطای دسترسی", action=f"term-subscription-grant:{term}", style="success")],
        [button("بازگشت", action=f"term-subscription-admin:{term}")],
    ))
    return Screen("\n".join(lines), keyboard(*rows))


def complimentary_search_results_screen(candidates: list[dict], *, term: int) -> Screen:
    lines = [f"<b>🔎 انتخاب دانشجوی ترم {to_persian_digits(term)}</b>", "", "نتیجه فقط از فهرست canonical سایت آمده است."]
    rows: list[list[dict]] = []
    for item in candidates[:12]:
        student = str(item.get("studentNumber") or "")
        name = str(item.get("name") or student)
        if not student.isdigit():
            continue
        access_path = str(dict(item.get("termAccess") or {}).get("accessPath") or "none")
        access_label = {
            "paid": "💳 پرداختی", "complimentary": "🎁 رایگان", "both": "✅ هر دو", "none": "🔒 بدون دسترسی",
        }.get(access_path, "🔒 بدون دسترسی")
        lines.append(f"• <b>{html.escape(name)}</b> · {to_persian_digits(student)} · {access_label}")
        rows.append([button(f"🎁 اعطای رایگان · {name[:20]}", action=f"term-subscription-grant-select:{term}:{student}")])
    if not rows:
        lines.append("نتیجهٔ قابل اعطایی پیدا نشد.")
    rows.append([button("انصراف", action=f"term-subscription-admin:{term}")])
    return Screen("\n".join(lines), keyboard(*rows))


def _product_status_label(item: dict) -> tuple[str, str]:
    status = str(item.get("effectiveStatus") or item.get("status") or "draft")
    return {
        "active": ("🟢", "فعال"),
        "scheduled": ("🕒", "زمان‌بندی‌شده"),
        "paused": ("⏸", "متوقف"),
        "expired": ("⌛", "منقضی"),
        "archived": ("🗄", "بایگانی"),
        "draft": ("📝", "پیش‌نویس"),
    }.get(status, ("⚪", "نامشخص"))


def payment_offers_screen(offers: list[dict], *, states: dict | None = None, page: int = 0) -> Screen:
    items = [item for item in offers if isinstance(item, dict)]
    state_map = dict(states or {})
    page_size = 6
    page = max(0, min(int(page), max(0, (len(items) - 1) // page_size)))
    visible = items[page * page_size:(page + 1) * page_size]
    rows: list[list[dict]] = []
    lines = ["<b>🛍 محصولات</b>", "", "محصول‌های قابل خرید برای حساب شما:"]
    for index, item in enumerate(visible, page * page_size + 1):
        title = str(item.get("title") or "آیتم خرید")
        price = format_rials(item.get("amountRials"))
        description = html.escape(str(item.get("description") or ""))
        offer_ref = str(item.get("ref") or "")
        state = dict(state_map.get(offer_ref) or {})
        max_per_user = max(0, int(item.get("maxPurchasesPerUser") or 0))
        success_count = max(0, int(state.get("successCount") or 0))
        paid = max_per_user > 0 and success_count >= max_per_user
        deadline = format_jalali_datetime(item.get("expiresAt"))
        capacity = max(0, int(item.get("capacity") or 0))
        reserved = max(0, int(state.get("reservedCount") or 0))
        remaining = max(0, capacity - reserved) if capacity else 0
        lines.extend(("", f"<b>{to_persian_digits(index)}. {html.escape(title)}</b>", f"<code>{html.escape(price)}</code>"))
        if description:
            lines.append(description[:220])
        if deadline:
            lines.append(f"مهلت: {html.escape(deadline)}")
        if capacity:
            lines.append(f"ظرفیت باقی‌مانده: <b>{to_persian_digits(remaining)}</b>")
        if offer_ref:
            if paid:
                lines.append("✅ پرداخت شده")
                order_token = str(state.get("latestSuccessOrderToken") or "")
                if re.fullmatch(r"[A-Za-z0-9_-]{20,46}", order_token):
                    rows.append([button(f"رسید · {title[:24]}", action=f"payment-status:{order_token}", style="success")])
            elif not capacity or remaining > 0:
                rows.append([button(f"مشاهده و پرداخت · {title[:20]}", action=f"payment-confirm:{offer_ref}", style="success")])
    if not items:
        lines.extend(("", "در حال حاضر محصول قابل خریدی برای این حساب وجود ندارد."))
    navigation: list[dict] = []
    if page > 0:
        navigation.append(button("قبلی", action=f"payments-page:{page - 1}"))
    if (page + 1) * page_size < len(items):
        navigation.append(button("بعدی", action=f"payments-page:{page + 1}"))
    if navigation:
        rows.append(navigation)
    rows.append([button("🏠 منوی اصلی", action="home")])
    return Screen("\n".join(lines), keyboard(*rows))


def payment_control_center_screen(offers: list[dict], summary: dict | None = None) -> Screen:
    local = {
        "active": sum(1 for item in offers if str(item.get("effectiveStatus") or item.get("status")) == "active"),
    }
    data = dict(summary or {})
    today = dict(data.get("today") or {})
    week = dict(data.get("week") or {})
    total = dict(data.get("total") or {})
    lines = [
        "<b>💳 مرکز کنترل پرداخت‌ها</b>", "",
        "<b>📊 خلاصه زنده</b>",
        f"امروز: <b>{to_persian_digits(today.get('successCount', 0))}</b> پرداخت · <code>{html.escape(format_rials(today.get('receivedRials', 0)))}</code>",
        f"۷ روز: <b>{to_persian_digits(week.get('successCount', 0))}</b> پرداخت · <code>{html.escape(format_rials(week.get('receivedRials', 0)))}</code>",
        f"کل: <b>{to_persian_digits(total.get('successCount', 0))}</b> موفق · <b>{to_persian_digits(total.get('pendingCount', 0))}</b> در انتظار",
        f"محصول فعال: <b>{to_persian_digits(local['active'])}</b>",
    ]
    if offers:
        lines.extend(("", "<b>آخرین محصولات</b>"))
        for item in offers[:3]:
            marker, label = _product_status_label(item)
            lines.append(f"• {marker} {html.escape(str(item.get('title') or 'محصول'))} · {label}")
    return Screen(
        "\n".join(lines),
        keyboard(
            [button("📚 اشتراک جزوات ترم ۷", action="term-subscription-admin:7", style="success")],
            [button("➕ محصول جدید", action="payment-offer-new", style="success")],
            [button("📦 محصولات", action="payment-products"), button("📊 آمار", action="payment-stats")],
            [button("🧾 تراکنش‌ها", action="payment-transactions"), button("🔎 جستجو", action="payment-search")],
            [button("👥 مخاطبان", action="payment-audiences"), button("📤 خروجی", action="payment-export")],
            [button("🔔 اعلان‌ها", action="payment-reminders"), button("⚙️ تنظیمات", action="payment-settings")],
            [button("↻ تازه‌سازی", action="admin-payments"), button("🏠 منوی اصلی", action="home")],
        ),
    )


def owner_payment_offers_screen(offers: list[dict], *, page: int = 0) -> Screen:
    items = [item for item in offers if isinstance(item, dict)]
    page_size = 8
    page = max(0, min(int(page), max(0, (len(items) - 1) // page_size)))
    visible = items[page * page_size:(page + 1) * page_size]
    lines = [
        "<b>📦 محصولات</b>",
        "",
        "تعریف محصول بین تلگرام و بله مشترک است؛ سفارش‌ها و وضعیت مالی در سایت ثبت می‌شوند.",
    ]
    rows: list[list[dict]] = []
    for item in visible:
        offer_ref = str(item.get("ref") or "")
        marker, status_label = _product_status_label(item)
        title = str(item.get("title") or "محصول پرداختی")
        lines.extend(("", f"{marker} <b>{html.escape(title)}</b>", f"<code>{html.escape(format_rials(item.get('amountRials')))}</code> · {status_label}"))
        rows.append([button(f"مدیریت · {title[:27]}", action=f"payment-offer:{offer_ref}")])
    if not items:
        lines.extend(("", "هنوز محصول پرداختی مستقلی در ربات ساخته نشده است."))
    navigation: list[dict] = []
    if page > 0:
        navigation.append(button("قبلی", action=f"payment-products-page:{page - 1}"))
    if (page + 1) * page_size < len(items):
        navigation.append(button("بعدی", action=f"payment-products-page:{page + 1}"))
    if navigation:
        rows.append(navigation)
    rows.extend((
        [button("➕ محصول جدید", action="payment-offer-new", style="success")],
        [button("بازگشت به پرداخت‌ها", action="admin-payments")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    return Screen("\n".join(lines), keyboard(*rows))


def payment_offer_wizard_screen(step: str, payload: dict) -> Screen:
    cancel = [button("لغو ساخت", action="payment-offer-cancel", style="danger")]
    if step == "title":
        return Screen(
            "<b>➕ محصول جدید · ۱ از ۴</b>\n\n<b>عنوان محصول</b> را در یک پیام بفرست.\n"
            "<blockquote>مثال: ثبت‌نام آزمون جامع</blockquote>",
            keyboard(cancel),
        )
    if step == "amount":
        title = html.escape(str(payload.get("title") or "محصول"))
        return Screen(
            f"<b>➕ محصول جدید · ۲ از ۴</b>\n\n{title}\n\nمبلغ را انتخاب کن یا مقدار دلخواه را به تومان وارد کن.",
            keyboard(
                [button("۵۰ هزار", action="payment-offer-amount:50000"), button("۱۰۰ هزار", action="payment-offer-amount:100000")],
                [button("۲۵۰ هزار", action="payment-offer-amount:250000"), button("۵۰۰ هزار", action="payment-offer-amount:500000")],
                [button("مبلغ دلخواه", action="payment-offer-custom-amount", style="primary")],
                cancel,
            ),
        )
    if step == "custom-amount":
        return Screen(
            "<b>➕ محصول جدید · مبلغ دلخواه</b>\n\nمبلغ را فقط به <b>تومان</b> بفرست.\n"
            "<blockquote>مثال: ۱۲۵۰۰۰</blockquote>",
            keyboard(cancel),
        )
    if step == "audience":
        return Screen(
            "<b>➕ محصول جدید · ۳ از ۴</b>\n\nچه کسانی این محصول را ببینند؟\n"
            "<blockquote>«دارندگان لینک» در فهرست محصولات دیده نمی‌شود و فقط با لینک امن باز می‌شود.</blockquote>",
            keyboard(
                [button("همه کاربران احرازشده", action="payment-offer-audience:all", style="success")],
                [button("ورودی ۱۴۰۲ دندان‌پزشکی تهران", action="payment-offer-audience:primary")],
                [button("فقط دارندگان لینک", action="payment-offer-audience:open")],
                [button("انتخاب افراد / فهرست", action="payment-offer-audience:advanced", style="primary")],
                cancel,
            ),
        )
    if step == "description":
        return Screen("<b>⚙️ توضیح محصول</b>\n\nتوضیح کوتاه را بفرست.", keyboard([button("بدون توضیح", action="payment-offer-no-description")], cancel))
    return payment_offer_preview_screen(payload)


def payment_offer_preview_screen(payload: dict) -> Screen:
    title = html.escape(str(payload.get("title") or "محصول"))
    description = html.escape(str(payload.get("description") or "بدون توضیح"))
    amount = html.escape(format_rials(payload.get("amountRials")))
    audience = html.escape(audience_label(payload.get("audience") or {"mode": "all"}))
    return Screen(
        f"<b>➕ محصول جدید · ۴ از ۴</b>\n\n<b>{title}</b>\n<blockquote>{description}</blockquote>\n"
        f"مبلغ نهایی: <code>{amount}</code>\nمخاطب: <b>{audience}</b>\n\nپس از تأیید، محصول فقط در ربات منتشر می‌شود.",
        keyboard(
            [button("تأیید و انتشار", action="payment-offer-publish", style="success")],
            [button("⚙️ توضیح اختیاری", action="payment-offer-description")],
            [button("ویرایش از ابتدا", action="payment-offer-new")],
            [button("لغو", action="payment-offer-cancel", style="danger")],
        ),
    )


def payment_offer_admin_detail_screen(item: dict, *, bot_username: str, platform: str = "telegram") -> Screen:
    title = html.escape(str(item.get("title") or "محصول"))
    description = html.escape(str(item.get("description") or "بدون توضیح"))
    amount = html.escape(format_rials(item.get("amountRials")))
    offer_ref = str(item.get("ref") or "")
    marker, status = _product_status_label(item)
    active = str(item.get("effectiveStatus") or item.get("status") or "") == "active"
    target = "paused" if active else "active"
    label = "⏸ توقف" if active else "▶️ فعال‌سازی"
    rows = [
        [button(label, action=f"payment-offer-status:{offer_ref}:{target}", style="danger" if active else "success")],
        [button("✏️ ویرایش", action=f"payment-offer-edit:{offer_ref}"), button("👥 مخاطب", action=f"payment-offer-audience-edit:{offer_ref}")],
        [button("🗓 زمان‌بندی", action=f"payment-offer-schedule:{offer_ref}"), button("⚙️ پیشرفته", action=f"payment-offer-advanced:{offer_ref}")],
        [button("📊 آمار", action=f"payment-offer-stats:{offer_ref}"), button("🧾 پرداخت‌کنندگان", action=f"payment-offer-payers:{offer_ref}")],
        [button("❌ پرداخت‌نکرده‌ها", action=f"payment-offer-unpaid:{offer_ref}"), button("📤 خروجی", action=f"payment-offer-export:{offer_ref}")],
        [button("📑 کپی محصول", action=f"payment-offer-duplicate:{offer_ref}"), button("🔄 تعویض لینک", action=f"payment-offer-rotate:{offer_ref}")],
    ]
    share_url = bot_start_url(bot_username, f"product_{str(item.get('shareToken') or '')}", platform=platform)
    if share_url:
        rows.append([button("🔗 لینک محصول", url=share_url, style="primary")])
        if platform == "telegram":
            rows.append([button("📤 اشتراک‌گذاری / Inline", switch_inline_query=title[:40])])
        else:
            rows.append([button("📤 اشتراک‌گذاری", url=share_url)])
        rows.append([button("📋 کپی اطلاعات", action=f"payment-product-copy:{offer_ref}")])
    if str(dict(item.get("audience") or {}).get("mode") or "all") in {"cohorts", "users", "lists"}:
        rows.append([button("👥 ارسال برای مخاطبان", action=f"payment-product-share-preview:{offer_ref}")])
    rows.extend((
        [button("🗄 بایگانی", action=f"payment-offer-delete-confirm:{offer_ref}", style="danger")],
        [button("بازگشت به محصولات", action="admin-payments")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    deadline = format_jalali_datetime(item.get("expiresAt")) or "بدون مهلت"
    audience = html.escape(audience_label(item.get("audience") or {"mode": "all"}))
    fulfillment = dict(item.get("fulfillment") or {})
    fulfillment_state = "فعال" if str(fulfillment.get("text") or "").strip() or str(fulfillment.get("url") or "").strip() else "تعریف نشده"
    return Screen(
        f"<b>💳 {title}</b>\n\n<blockquote>{description}</blockquote>\n"
        f"مبلغ: <code>{amount}</code>\nوضعیت: <b>{marker} {status}</b>\n"
        f"مخاطب: <b>{audience}</b>\nمهلت: {html.escape(deadline)}\n"
        f"تحویل پس از خرید: <b>{fulfillment_state}</b>\n"
        f"نسخه: <code>{to_persian_digits(item.get('version', 1))}</code>",
        keyboard(*rows),
    )


def payment_offer_delete_confirmation(item: dict) -> Screen:
    title = html.escape(str(item.get("title") or "محصول"))
    offer_ref = str(item.get("ref") or "")
    return Screen(
        f"<b>🗄 بایگانی محصول</b>\n\nمحصول «{title}» از دسترس خرید خارج و بایگانی شود؟\n"
        "<blockquote>سفارش‌ها و سوابق پرداخت قبلی سایت حذف نمی‌شوند.</blockquote>",
        keyboard(
            [button("بایگانی", action=f"payment-offer-delete:{offer_ref}", style="danger")],
            [button("انصراف", action=f"payment-offer:{offer_ref}")],
        ),
    )


def payment_offer_saved_screen(item: dict, *, bot_username: str = "", platform: str = "telegram") -> Screen:
    title = html.escape(str(item.get("title") or "محصول پرداختی"))
    amount = html.escape(format_rials(item.get("amountRials")))
    share_url = bot_start_url(bot_username, f"product_{str(item.get('shareToken') or '')}", platform=platform)
    rows = [[button("مدیریت همین محصول", action=f"payment-offer:{item.get('ref', '')}")]]
    if share_url:
        rows.append([button("🔗 لینک محصول", url=share_url, style="primary")])
    rows.extend(([button("مدیریت محصولات", action="payment-products")], [button("🏠 منوی اصلی", action="home")]))
    return Screen(
        f"<b>🟢 محصول ربات ساخته شد</b>\n\n{title}\n<blockquote>{amount}</blockquote>\n"
        "این محصول فقط در منوی پرداخت ربات فعال است و وارد کاتالوگ سایت نشده است.",
        keyboard(*rows),
    )


def payment_confirm_screen(item: dict, *, action_ref: str = "", state: dict | None = None) -> Screen:
    offer_ref = str(item.get("ref") or "")
    title = html.escape(str(item.get("title") or "آیتم خرید"))
    amount = html.escape(format_rials(item.get("amountRials")))
    product_state = dict(state or {})
    max_per_user = max(0, int(item.get("maxPurchasesPerUser") or 0))
    paid = max_per_user > 0 and int(product_state.get("successCount") or 0) >= max_per_user
    capacity = max(0, int(item.get("capacity") or 0))
    remaining = max(0, capacity - int(product_state.get("reservedCount") or 0)) if capacity else 0
    create_action = action_ref or f"payment-create:{offer_ref}"
    rows: list[list[dict]] = []
    if paid:
        token = str(product_state.get("latestSuccessOrderToken") or "")
        if re.fullmatch(r"[A-Za-z0-9_-]{20,46}", token):
            rows.append([button("✅ مشاهده رسید", action=f"payment-status:{token}", style="success")])
    elif not capacity or remaining > 0:
        rows.append([button(f"پرداخت {amount}", action=create_action, style="success")])
    rows.append([button("بازگشت به محصولات", action="payments"), button("🏠 منوی اصلی", action="home")])
    status_copy = "✅ این محصول قبلاً پرداخت شده است." if paid else ("ظرفیت این محصول تکمیل شده است." if capacity and remaining <= 0 else "پس از لمس دکمه پرداخت، سفارش در سایت ساخته می‌شود و به درگاه امن منتقل می‌شوی.")
    return Screen(
        f"<b>🛍 {title}</b>\n\n<blockquote>{html.escape(str(item.get('description') or 'بدون توضیح'))}</blockquote>\n"
        f"مبلغ: <code>{amount}</code>\n\n{status_copy}",
        keyboard(*rows),
    )


def payment_created_screen(
    payload: dict,
    *,
    platform: str = "telegram",
    return_to_bot_enabled: bool = False,
) -> Screen:
    amount = html.escape(format_rials(payload.get("amountRials")))
    order_token = str(payload.get("orderToken") or "")
    platform_label = "بله" if platform == "bale" else "تلگرام"
    rows = [[button("ورود به درگاه پرداخت", url=str(payload.get("redirectUrl") or ""), style="success")]]
    if re.fullmatch(r"[A-Za-z0-9_-]{20,46}", order_token):
        rows.append([button("بررسی وضعیت پرداخت", action=f"payment-status:{order_token}")])
    rows.append([button("🏠 منوی اصلی", action="home")])
    completion_copy = (
        f"پس از پایان درگاه به همین ربات در <b>{platform_label}</b> برمی‌گردی. "
        if return_to_bot_enabled
        else "پس از پرداخت، وضعیت سفارش را از همین صفحه بررسی کن. "
    )
    return Screen(
        f"<b>🟢 سفارش آماده پرداخت است</b>\n\nمبلغ نهایی\n<blockquote>{amount}</blockquote>\n"
        + completion_copy
        + "تأیید نهایی فقط بعد از callback معتبر و بررسی سمت سایت انجام می‌شود.",
        keyboard(*rows),
    )


def payment_status_screen(
    payload: dict,
    *,
    platform: str = "telegram",
    order_token: str = "",
    return_to_bot_enabled: bool = False,
) -> Screen:
    labels = {
        "pending": ("🟡 در انتظار پرداخت", "پرداخت هنوز توسط درگاه تأیید نشده است."),
        "success": ("🟢 پرداخت موفق", "پرداخت توسط سایت و درگاه تأیید شده است."),
        "failed": ("🔴 پرداخت ناموفق", "پرداخت تأیید نشد."),
        "canceled": ("⚪ پرداخت لغوشده", "این سفارش لغو شده است."),
        "expired": ("⚪ پرداخت منقضی", "مهلت این سفارش پایان یافته است."),
    }
    status = str(payload.get("status") or "pending")
    title, description = labels.get(status, labels["pending"])
    amount = html.escape(format_rials(payload.get("amountRials")))
    platform_label = "بله" if platform == "bale" else "تلگرام"
    rows: list[list[dict]] = []
    if re.fullmatch(r"[A-Za-z0-9_-]{20,46}", order_token) and status == "pending":
        rows.append([button("بررسی دوباره", action=f"payment-status:{order_token}", style="primary")])
    if not return_to_bot_enabled:
        result_url = _safe_exam_url(payload.get("resultUrl"))
        if result_url:
            rows.append([button("مشاهده رسید سایت", url=result_url, style="primary")])
    rows.extend((
        [button("🛍 محصولات", action="payments")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    completion_copy = f"نتیجهٔ این خرید در همان ربات <b>{platform_label}</b> نمایش داده می‌شود."
    item_title = html.escape(str(payload.get("title") or "خرید"))
    paid_at = format_jalali_datetime(payload.get("verifiedAt") or payload.get("paidAt"))
    tracking = html.escape(str(payload.get("trackingRef") or payload.get("refId") or "—"))
    fulfillment = dict(payload.get("fulfillment") or {})
    fulfillment_text = html.escape(str(fulfillment.get("text") or "").strip()[:600])
    fulfillment_url = _safe_exam_url(fulfillment.get("url"))
    if status == "success" and fulfillment_url:
        rows.insert(0, [button("🎁 دریافت محصول", url=fulfillment_url, style="success")])
    return Screen(
        f"<b>{title}</b>\n\n<b>{item_title}</b>\n{html.escape(description)}\n<blockquote>{amount}</blockquote>\n"
        + (f"زمان: {html.escape(paid_at)}\n" if paid_at else "")
        + f"کد پیگیری: <code>{tracking}</code>\n"
        + (f"\n<b>🎁 تحویل</b>\n{fulfillment_text}\n" if status == "success" and fulfillment_text else "")
        + completion_copy,
        keyboard(*rows),
    )


def payment_success_push_screen(payload: dict, *, platform: str = "telegram") -> Screen:
    status = str(payload.get("status") or "")
    if status != "success":
        raise ValueError("Only verified successful payments may be pushed")
    title = html.escape(" ".join(str(payload.get("title") or "خرید").split())[:160])
    amount = html.escape(format_rials(payload.get("amountRials")))
    order_token = str(payload.get("orderToken") or "")
    rows: list[list[dict]] = []
    if re.fullmatch(r"[A-Za-z0-9_-]{20,46}", order_token):
        rows.append([button("جزئیات پرداخت", action=f"payment-status:{order_token}", style="success")])
    fulfillment = dict(payload.get("fulfillment") or {})
    fulfillment_url = _safe_exam_url(fulfillment.get("url"))
    fulfillment_text = html.escape(str(fulfillment.get("text") or "").strip()[:600])
    if fulfillment_url:
        rows.append([button("🎁 دریافت محصول", url=fulfillment_url, style="success")])
    rows.extend((
        [button("🛍 محصولات", action="payments")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    platform_label = "بله" if platform == "bale" else "تلگرام"
    verified = html.escape(format_jalali_datetime(payload.get("verifiedAt") or payload.get("paidAt")) or "—")
    tracking = html.escape(str(payload.get("trackingRef") or "—"))
    return Screen(
        f"<b>✅ پرداخت با موفقیت تأیید شد</b>\n\n<b>{title}</b>\n"
        f"<blockquote>{amount}</blockquote>\n"
        f"زمان: {verified}\nکد پیگیری: <code>{tracking}</code>\n"
        + (f"\n<b>🎁 تحویل</b>\n{fulfillment_text}\n" if fulfillment_text else "")
        + f"این نتیجه پس از تأیید درگاه، فقط در همان ربات <b>{platform_label}</b> ارسال شده است.",
        keyboard(*rows),
    )


def payment_owner_success_push_screen(payload: dict) -> Screen:
    title = html.escape(str(payload.get("title") or "خرید"))
    payer = html.escape(str(payload.get("payerName") or "—"))
    student = html.escape(str(payload.get("studentNumber") or "—"))
    amount = html.escape(format_rials(payload.get("amountRials")))
    verified = html.escape(format_jalali_datetime(payload.get("verifiedAt")) or "—")
    tracking = html.escape(str(payload.get("trackingRef") or "—"))
    offer_ref = str(payload.get("offerRef") or "")
    rows = []
    if offer_ref:
        rows.append([button("مشاهده محصول", action=f"payment-offer:{offer_ref}")])
    rows.extend(([button("همه تراکنش‌ها", action="payment-transactions")], [button("مرکز پرداخت‌ها", action="admin-payments")]))
    return Screen(
        f"<b>💰 پرداخت جدید</b>\n\nنام: <b>{payer}</b>\nشماره دانشجویی: <code>{student}</code>\n"
        f"محصول: <b>{title}</b>\nمبلغ: <code>{amount}</code>\nزمان: {verified}\nکد پیگیری: <code>{tracking}</code>",
        keyboard(*rows),
    )


def payment_product_report_screen(item: dict, report: dict, *, period: str = "all") -> Screen:
    title = html.escape(str(item.get("title") or "محصول"))
    counts = dict(report.get("counts") or {})
    target = report.get("targetCount")
    paid = max(0, int(counts.get("success") or 0))
    unique = max(0, int(counts.get("uniquePayers") or 0))
    pending = max(0, int(counts.get("pending") or 0))
    failed = max(0, int(counts.get("failed") or 0))
    unpaid = max(0, int(report.get("unpaidCount") or 0)) if target is not None else None
    conversion = "—" if not target else f"{(unique * 100 / max(1, int(target))):.1f}%"
    offer_ref = str(item.get("ref") or "")
    capacity = max(0, int(item.get("capacity") or 0))
    remaining = max(0, capacity - paid - pending) if capacity else None
    period_label = {"today": "امروز", "7": "۷ روز", "30": "۳۰ روز", "all": "کل"}.get(period, "بازه سفارشی")
    target_label = "—" if target is None else to_persian_digits(target)
    deadline = format_jalali_datetime(item.get("expiresAt")) or "بدون مهلت"
    created = format_jalali_datetime(item.get("createdAt")) or "—"
    return Screen(
        f"<b>📊 آمار · {title}</b>\n<blockquote>بازه: {period_label}</blockquote>\n"
        f"مبلغ: <code>{html.escape(format_rials(item.get('amountRials')))}</code> · مخاطب هدف: <b>{target_label}</b>\n"
        f"موفق: <b>{to_persian_digits(paid)}</b> · خریدار یکتا: <b>{to_persian_digits(unique)}</b>\n"
        f"در انتظار: <b>{to_persian_digits(pending)}</b> · ناموفق: <b>{to_persian_digits(failed)}</b>\n"
        + (f"پرداخت‌نکرده: <b>{to_persian_digits(unpaid)}</b>\n" if unpaid is not None else "")
        + (f"ظرفیت باقی‌مانده: <b>{to_persian_digits(remaining)}</b> از <b>{to_persian_digits(capacity)}</b>\n" if remaining is not None else "")
        + f"نرخ تبدیل: <b>{to_persian_digits(conversion)}</b>\n"
        f"دریافتی: <code>{html.escape(format_rials(report.get('receivedRials', 0)))}</code>\n"
        f"در انتظار: <code>{html.escape(format_rials(report.get('pendingRials', 0)))}</code>\n"
        f"مهلت: {html.escape(deadline)}\nساخته‌شده: {html.escape(created)}",
        keyboard(
            [button("امروز", action=f"payment-offer-stats:{offer_ref}:today"), button("۷ روز", action=f"payment-offer-stats:{offer_ref}:7"), button("۳۰ روز", action=f"payment-offer-stats:{offer_ref}:30")],
            [button("کل", action=f"payment-offer-stats:{offer_ref}:all", style="primary")],
            [button("✅ پرداخت‌کنندگان", action=f"payment-offer-payers:{offer_ref}"), button("❌ پرداخت‌نکرده‌ها", action=f"payment-offer-unpaid:{offer_ref}")],
            [button("📤 CSV", action=f"payment-export-file:{offer_ref}:csv"), button("📝 متن", action=f"payment-export-text:{offer_ref}")],
            [button("بازگشت", action=f"payment-offer:{offer_ref}"), button("مرکز پرداخت‌ها", action="admin-payments")],
        ),
    )


def payment_transactions_screen(payload: dict, *, page: int = 0, filters: dict | None = None) -> Screen:
    items = [item for item in payload.get("items", []) if isinstance(item, dict)]
    total = max(0, int(payload.get("total") or len(items)))
    active_filters = {key: value for key, value in dict(filters or {}).items() if value}
    lines = ["<b>🧾 تراکنش‌ها</b>", f"<blockquote>{to_persian_digits(total)} نتیجه · صفحه {to_persian_digits(page + 1)}</blockquote>"]
    detail_rows: list[list[dict]] = []
    for item in items[:10]:
        status = html.escape(str(item.get("statusLabel") or item.get("status") or "نامشخص"))
        order_id = max(0, int(item.get("orderId") or 0))
        lines.append(
            f"• <b>{html.escape(str(item.get('payerName') or '—'))}</b> · "
            f"{html.escape(str(item.get('title') or 'محصول'))}\n"
            f"  <code>{html.escape(format_rials(item.get('amountRials')))}</code> · {status} · "
            f"{html.escape(format_jalali_datetime(item.get('createdAt')))}"
        )
        if order_id:
            detail_rows.append([button(f"جزئیات سفارش {to_persian_digits(order_id)}", action=f"payment-transaction:{order_id}")])
    if not items:
        lines.append("تراکنشی با این فیلتر پیدا نشد.")
    rows = detail_rows
    navigation: list[dict] = []
    if page > 0:
        navigation.append(button("قبلی", action=f"payment-transactions-page:{page - 1}"))
    if (page + 1) * 10 < total:
        navigation.append(button("بعدی", action=f"payment-transactions-page:{page + 1}"))
    if navigation:
        rows.append(navigation)
    rows.extend([
        [button("🔎 جستجو", action="payment-search"), button("🧰 فیلترها", action="payment-transaction-filters", style="primary")],
        [button("📤 CSV", action="payment-export-file:filtered:csv"), button("📝 خروجی متنی", action="payment-export-text:filtered")],
        *([[button("پاک‌کردن فیلترها", action="payment-tx-clear")]] if active_filters else []),
        [button("مرکز پرداخت‌ها", action="admin-payments")],
    ])
    return Screen("\n".join(lines), keyboard(*rows))


def payment_transaction_filters_screen(filters: dict, offers: list[dict]) -> Screen:
    labels = []
    if filters.get("status"):
        labels.append(f"وضعیت: {filters['status']}")
    if filters.get("platform"):
        labels.append(f"پلتفرم: {filters['platform']}")
    if filters.get("gateway"):
        labels.append(f"درگاه: {filters['gateway']}")
    if filters.get("offerRef"):
        labels.append("محصول مشخص")
    if filters.get("dateFrom") or filters.get("dateTo"):
        labels.append("بازه زمانی")
    rows = [
        [button("موفق", action="payment-tx-status:success"), button("در انتظار", action="payment-tx-status:pending")],
        [button("ناموفق", action="payment-tx-status:failed"), button("لغوشده", action="payment-tx-status:canceled")],
        [button("تلگرام", action="payment-tx-platform:telegram"), button("بله", action="payment-tx-platform:bale")],
        [button("زیبال", action="payment-tx-gateway:zibal"), button("زرین‌پال", action="payment-tx-gateway:zarinpal")],
        [button("امروز", action="payment-tx-period:today"), button("۷ روز", action="payment-tx-period:7"), button("۳۰ روز", action="payment-tx-period:30")],
        [button("بازه سفارشی", action="payment-tx-period:custom"), button("انتخاب محصول", action="payment-tx-product")],
    ]
    for item in offers[:8]:
        rows.append([button(f"📦 {str(item.get('title') or 'محصول')[:28]}", action=f"payment-tx-product:{item.get('ref', '')}")])
    rows.extend(([button("نمایش نتایج", action="payment-transactions", style="success")], [button("پاک‌کردن همه", action="payment-tx-clear")]))
    summary = " · ".join(labels) if labels else "بدون فیلتر"
    return Screen(f"<b>🧰 فیلتر تراکنش‌ها</b>\n\n<blockquote>{html.escape(summary)}</blockquote>\nهر انتخاب جایگزین فیلتر همان گروه می‌شود.", keyboard(*rows))


def payment_transaction_detail_screen(payload: dict) -> Screen:
    order = dict(payload.get("order") or payload)
    order_id = max(0, int(order.get("orderId") or 0))
    status = str(order.get("status") or "pending")
    lines = [
        f"<b>🧾 سفارش {to_persian_digits(order_id)}</b>", "",
        f"کاربر: <b>{html.escape(str(order.get('payerName') or '—'))}</b>",
        f"شماره دانشجویی: <code>{html.escape(str(order.get('studentNumber') or '—'))}</code>",
        f"محصول: <b>{html.escape(str(order.get('title') or 'محصول'))}</b>",
        f"مبلغ: <code>{html.escape(format_rials(order.get('amountRials')))}</code>",
        f"وضعیت: <b>{html.escape(str(order.get('statusLabel') or status))}</b>",
        f"ایجاد: {html.escape(format_jalali_datetime(order.get('createdAt')) or '—')}",
        f"شروع پرداخت: {html.escape(format_jalali_datetime(order.get('paymentStartedAt')) or '—')}",
        f"پرداخت: {html.escape(format_jalali_datetime(order.get('paidAt')) or '—')}",
        f"تأیید: {html.escape(format_jalali_datetime(order.get('verifiedAt')) or '—')}",
        f"درگاه: {html.escape(str(order.get('gateway') or '—'))}",
        f"پلتفرم: {html.escape(str(order.get('originPlatform') or '—'))}",
        f"کد پیگیری: <code>{html.escape(str(order.get('trackingRef') or '—'))}</code>",
    ]
    rows: list[list[dict]] = []
    if order_id and status != "success":
        rows.extend((
            [button("بازگردانی به انتظار", action=f"payment-transaction-status:{order_id}:pending")],
            [button("ناموفق", action=f"payment-transaction-status:{order_id}:failed"), button("لغوشده", action=f"payment-transaction-status:{order_id}:canceled")],
            [button("منقضی", action=f"payment-transaction-status:{order_id}:expired", style="danger")],
        ))
    rows.extend(([button("بازگشت به تراکنش‌ها", action="payment-transactions")], [button("مرکز پرداخت‌ها", action="admin-payments")]))
    return Screen("\n".join(lines), keyboard(*rows))


def payment_people_screen(title: str, people: list[dict], *, offer_ref: str, kind: str, page: int = 0) -> Screen:
    page = max(0, int(page))
    page_size = 20
    subset = people[page * page_size:(page + 1) * page_size]
    lines = [f"<b>{html.escape(title)}</b>", f"<blockquote>{to_persian_digits(len(people))} نفر · صفحه {to_persian_digits(page + 1)}</blockquote>"]
    for index, person in enumerate(subset, page * page_size + 1):
        lines.append(f"{to_persian_digits(index)}. {html.escape(str(person.get('name') or '—'))} · <code>{html.escape(str(person.get('studentNumber') or '—'))}</code>")
    if not subset:
        lines.append("موردی پیدا نشد.")
    rows = [[button("📤 CSV", action=f"payment-export-file:{offer_ref}:{kind}")]]
    navigation: list[dict] = []
    prefix = "payment-offer-payers" if kind == "paid" else "payment-offer-unpaid"
    if page > 0:
        navigation.append(button("قبلی", action=f"{prefix}:{offer_ref}:{page - 1}"))
    if (page + 1) * page_size < len(people):
        navigation.append(button("بعدی", action=f"{prefix}:{offer_ref}:{page + 1}"))
    if navigation:
        rows.append(navigation)
    if kind == "unpaid" and people:
        rows.append([button("🔔 پیش‌نمایش یادآوری", action=f"payment-reminder-preview:{offer_ref}", style="primary")])
    rows.extend(([button("بازگشت به آمار", action=f"payment-offer-stats:{offer_ref}")], [button("مرکز پرداخت‌ها", action="admin-payments")]))
    return Screen("\n".join(lines), keyboard(*rows))


def student_assistant_screen(payload: dict, *, is_owner: bool = False) -> Screen:
    view = dict(payload.get("view") or payload)
    title = html.escape(" ".join(str(view.get("title") or "دستیار دانشجو").split())[:120])
    description = html.escape(str(view.get("description") or "").strip()[:900])
    status = html.escape(str(view.get("statusText") or "").strip()[:300])
    lines = [f"<b>🎓 {title}</b>"]
    if description:
        lines.extend(("", description))
    if status:
        lines.extend(("", f"<blockquote>{status}</blockquote>"))

    connectors = [item for item in view.get("connectors", []) if isinstance(item, dict)][:3]
    if connectors:
        lines.extend(("", "<b>اتصال سامانه‌ها</b>"))
        for item in connectors:
            label = html.escape(" ".join(str(item.get("label") or "سامانه").split())[:50])
            state = html.escape(" ".join(str(item.get("statusLabel") or "نامشخص").split())[:60])
            lines.append(f"• {label}: {state}")

    items = [item for item in view.get("items", []) if isinstance(item, dict)][:6]
    if items:
        lines.extend(("", "<b>کارهای جاری</b>"))
        for item in items:
            label = html.escape(" ".join(str(item.get("label") or "عملیات").split())[:80])
            detail = html.escape(" ".join(str(item.get("detail") or "").split())[:120])
            lines.append(f"• {label}" + (f" — {detail}" if detail else ""))

    rows: list[list[dict]] = []
    for action in [item for item in view.get("actions", []) if isinstance(item, dict)][:6]:
        ref = str(action.get("ref") or "")
        label = " ".join(str(action.get("label") or "ادامه").split())[:42]
        style = str(action.get("style") or "")
        if re.fullmatch(r"[A-Za-z0-9_-]{1,20}", ref) is None or not label:
            continue
        rows.append([button(label, action=f"assistant-action:{ref}", style=style if style in {"primary", "success", "danger"} else "")])
    rows.extend((
        [button("↻ تازه‌سازی", action="student-assistant", style="primary")],
        [button("🏠 منوی اصلی", action="home")],
    ))
    return Screen("\n".join(lines), keyboard(*rows))


def integration_challenge_waiting_screen(payload: dict) -> Screen:
    connector = str(payload.get("connector") or dict(payload.get("challenge") or {}).get("connector") or "")
    labels = {"navid": "نوید", "food": "تغذیه", "saba": "سرویس"}
    label = labels.get(connector, "سامانه دانشگاه")
    return Screen(
        f"<b>🔐 کپچای {html.escape(label)} ارسال شد</b>\n\n"
        "تصویر در پیوی همین حساب فرستاده شد. کد را فقط با Reply به همان تصویر بفرست؛ "
        "هر پاسخ فقط یک‌بار و برای همان عملیات پذیرفته می‌شود.",
        keyboard(
            [button("لغو یا مشاهده وضعیت", action="student-assistant")],
            [button("🏠 منوی اصلی", action="home")],
        ),
    )


def home(
    site_url: str,
    *,
    is_owner: bool,
    student_assistant_enabled: bool = False,
    has_products: bool = False,
) -> Screen:
    rows = [
        [button("📝 آزمون‌ها", action="exams", style="primary"), button("📚 جزوات", action="notes", style="success")],
        [button("🔐 اشتراک جزوات", action="term-subscription:7")],
        [button("📊 نمرات", action="grades"), button("👤 حساب من", action="account")],
        [button("🔔 اعلان‌ها", action="notifications"), button("🛟 راهنما", action="help")],
    ]
    if not is_owner and has_products:
        rows.insert(0, [button("🛍 محصولات", action="payments", style="success")])
    if student_assistant_enabled:
        rows.append([button("🎓 دستیار دانشجو", action="student-assistant", style="primary")])
    if is_owner:
        rows.insert(0, [button("💳 پرداخت‌ها", action="admin-payments", style="success")])
        rows.append([button("🎓 مرکز نوید", action="navid", style="success")])
        rows.append([button("⚙️ مدیریت دنت‌یار", action="admin", style="primary")])
    return Screen(
        frame(
            "دنت‌یار | ورودی ۱۴۰۲",
            "آزمون‌ها، جزوات و وضعیت آموزشی شما در یک مسیر ساده.",
            "برای نمایش اطلاعات شخصی، اتصال امن حساب سایت لازم است.",
        ),
        keyboard(*rows),
    )


def section(name: str, site_url: str, *, is_owner: bool) -> Screen:
    home_row = [button("🏠 منوی اصلی", action="home")]
    if name == "exams":
        return Screen(
            frame("📝 آزمون‌ها", "ورود به آزمون‌ها و مرور نتایج ثبت‌شده.", "اطلاعات شخصی فقط پس از احراز حساب سایت نمایش داده می‌شود."),
            keyboard(
                [button("ورود به آزمون‌ها", url=f"{site_url}/exams/", style="primary")],
                [button("آزمون‌های من", action="link-required")],
                home_row,
            ),
        )
    if name == "notes":
        return Screen(
            frame("📚 جزوات", "درس، جلسه و نوع فایل را داخل همین ربات انتخاب کن.", "مسیر قدیمی سایت و ارسال جزوه از این دکمه بازنشسته شده است."),
            keyboard(home_row),
        )
    if name == "grades":
        return Screen(
            frame("📊 نمرات", "نمایش کارنامه و وضعیت ثبت نمرات.", "ربات بدون اتصال حساب، نمره یا اطلاعات دانشجو را نمایش نمی‌دهد."),
            keyboard([button("باز کردن نمرات", url=f"{site_url}/grades/", style="primary")], home_row),
        )
    if name == "account":
        return Screen(
            frame("👤 حساب من", "مدیریت حساب، دستگاه‌های ثبت‌شده و نشست‌های فعال.", "اتصال Telegram به حساب سایت هنوز فعال نشده است."),
            keyboard(
                [button("حساب کاربری سایت", url=f"{site_url}/account/", style="primary")],
                [button("اتصال امن حساب", action="link-required")],
                home_row,
            ),
        )
    if name == "notifications":
        return Screen(
            frame("🔔 اعلان‌ها", "اعلان‌های آموزشی و عملیاتی مهم از همین بات ارسال می‌شوند.", "تنظیمات شخصی اعلان پس از اتصال حساب فعال می‌شود."),
            keyboard([button("تنظیم اعلان‌ها", action="link-required")], home_row),
        )
    if name == "help":
        return Screen(
            frame("🛟 راهنما", "از دکمه‌های منوی اصلی استفاده کنید. برای اطلاعات شخصی ابتدا حساب سایت را متصل کنید.", "ربات رمز سایت را نمی‌گیرد؛ فقط در ثبت اولیه، کد کوتاه‌عمر تأیید موبایل را دریافت می‌کند."),
            keyboard([button("باز کردن سایت", url=f"{site_url}/app/", style="primary")], home_row),
        )
    if name == "admin" and is_owner:
        return Screen(
            frame("⚙️ مدیریت دنت‌یار", "ورودی‌های مدیریتی و وضعیت سرویس‌ها فقط برای مالک نمایش داده می‌شود.", "Telegram notifier فعال است؛ اتصال APIهای سایت در مرحله بعد انجام می‌شود."),
            keyboard(
                [button("پنل مدیریت سایت", url=f"{site_url}/admin/", style="primary")],
                [button("📊 مدیریت نمرات", action="admin-grades")],
                [button("🎓 مرکز نوید", action="navid", style="success")],
                [button("✏️ درخواست‌های ویرایش مشخصات", action="profile-edit-requests")],
                [button("🔗 مدیریت اتصال حساب‌ها", action="identity-mappings")],
                [button("💳 محصولات پرداخت", action="admin-payments", style="success")],
                [button("وضعیت سرویس‌ها", action="system-status")],
                home_row,
            ),
        )
    if name == "system-status" and is_owner:
        return Screen(
            frame("🖥 وضعیت سرویس‌ها", "Dent1402Bot و notifier مرکزی روی VPS فعال‌اند.", "اتصال Bale، API سایت و backup لپ‌تاپ هنوز تکمیل نشده‌اند."),
            keyboard([button("تازه‌سازی", action="system-status", style="primary")], home_row),
        )
    return home(site_url, is_owner=is_owner)
