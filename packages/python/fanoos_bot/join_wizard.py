"""The student join wizard: screens and pure step logic.

Ported from legacy/bot/dent_bot/onboarding.py, which ran against real
students -- reuse its Persian strings verbatim wherever the underlying
concept still holds. Three deliberate differences from that source, all
called out where they matter below:

1. Reply keyboards, not ui_v3 inline screens (the owner's explicit request;
   see botapi.py's send_reply_keyboard/remove_reply_keyboard).
2. No two-path gateway: legacy offered a privileged "دانشجوی ورودی ۱۴۰۲
   دندانپزشکی تهران هستم" shortcut beside the generic wizard because one
   cohort was special. In FANOOS no class is special, so there is exactly
   one wizard and it starts directly at first-name.
3. A "faculty" step that legacy never had. Legacy picked "major" (a global,
   hardcoded 3-item list) before province/institution, because one project
   only ever offered three fields. FANOOS's directory is per-institution
   (institution -> faculty -> program), and AGENTS.md #1 forbids hardcoding
   program identity into application logic, so "major" is replaced by a
   directory-driven "faculty" then "program" step, positioned after
   institution rather than before province. This makes the total step count
   13 (12 for آزاد) where legacy had 12 (11 for آزاد).

Entry year, entry term and course type stay legacy's own static lists
(۱۳۹۹-۱۴۰۵, نیمسال اول/دوم, and the three course types) rather than becoming
directory-driven -- that was an explicit product decision: these are not
institution/program/cohort identity, they are the same generic academic
vocabulary regardless of which class a student ends up in, and whether a
live class actually exists for the chosen (program, entry_year) is checked
once, after phone verification, rather than by filtering the list up front.
"""

from __future__ import annotations

import html
import re
from dataclasses import dataclass
from typing import Any


CANCEL = "انصراف"
BACK_STEP = "↩️ مرحله قبل"
NEXT_PAGE = "صفحه بعد ◀️"
PREVIOUS_PAGE = "▶️ صفحه قبل"
SKIP_STUDENT_NUMBER = "شماره دانشجویی ندارم"
CONFIRM_PROFILE = "✅ تأیید اطلاعات"
RESTART_PROFILE = "✏️ شروع دوباره"
SHARE_CONTACT = "📱 ارسال شماره موبایل من"
RESEND_OTP = "ارسال مجدد کد"
CHANGE_PHONE = "تغییر شماره موبایل"
REQUEST_CLASS_CREATION = "📝 درخواست ساخت کلاس"
REQUEST_UPGRADE = "✋ درخواست تأیید نماینده"

ENTRY_YEARS: tuple[str, ...] = ("۱۳۹۹", "۱۴۰۰", "۱۴۰۱", "۱۴۰۲", "۱۴۰۳", "۱۴۰۴", "۱۴۰۵")
ENTRY_TERMS: tuple[str, ...] = ("نیمسال اول", "نیمسال دوم")
COURSE_TYPES: tuple[str, ...] = ("روزانه یا تعهدی", "شهریه پرداز", "بین الملل")

_PERSIAN_DIGITS = str.maketrans("0123456789", "۰۱۲۳۴۵۶۷۸۹")
_ASCII_DIGITS = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")

# Base step order. "course-type" is dropped from the effective sequence for
# آزاد institutions (institution_type == 'azad_university'), matching
# legacy's own azad-skip -- just generalized to this longer base sequence.
STEP_ORDER: tuple[str, ...] = (
    "first-name", "last-name", "province", "institution", "faculty", "program",
    "entry-year", "entry-term", "course-type", "student-number",
    "review", "contact", "otp",
)

PAGE_SIZE = 10


def normalize_digits(value: str) -> str:
    return (value or "").translate(_ASCII_DIGITS)


def to_fa_digits(value: str) -> str:
    return normalize_digits(value).translate(_PERSIAN_DIGITS)


def clean_text(value: str, max_length: int) -> str:
    value = re.sub(r"\s+", " ", (value or "").strip())
    return value[:max_length]


def normalize_student_number(value: str) -> str:
    return re.sub(r"\D+", "", normalize_digits(value))


@dataclass(frozen=True)
class WizardScreen:
    """A reply-keyboard screen, in both Telegram-rich-message HTML and Bale
    plain-text form. The runtime picks whichever the platform needs."""

    html: str
    keyboard: tuple[tuple[dict, ...], ...]
    placeholder: str = ""

    def bale_text(self) -> str:
        return html_to_bale_text(self.html)


def reply_keyboard(*rows: list) -> tuple[tuple[dict, ...], ...]:
    return tuple(
        tuple(item if isinstance(item, dict) else {"text": item} for item in row)
        for row in rows
    )


def html_to_bale_text(text: str) -> str:
    """Bale formats outgoing text as Markdown with single-asterisk bold
    (packages/python/fanoos_bot/ui_v3/providers/bale.py's _safe_bold), not
    Telegram HTML. Convert the same emphasis, drop tags Bale has no
    equivalent for, and unescape entities used for dynamic HTML content."""
    text = re.sub(r"</?u>", "", text)
    text = re.sub(r"</?b>", "*", text)
    text = re.sub(r"</?code>", "`", text)
    text = re.sub(r"</?blockquote>", "", text)
    text = re.sub(r"<[^>]+>", "", text)
    return html.unescape(text).strip()


def _step_title(step: str, icon: str, title: str, *, azad: bool) -> str:
    position = STEP_ORDER.index(step) + 1
    total = len(STEP_ORDER)
    if azad:
        course_type_position = STEP_ORDER.index("course-type") + 1
        total -= 1
        if position > course_type_position:
            position -= 1
    current = to_fa_digits(str(position))
    total_fa = to_fa_digits(str(total))
    return f"<b><u>{icon} {html.escape(title)}</u></b>\n<code>مرحله {current} از {total_fa}</code>"


def first_name_screen() -> WizardScreen:
    return WizardScreen(
        f"{_step_title('first-name', '👤', 'نام', azad=False)}\n\n"
        "✨ نامت را همان‌طور که در دانشگاه ثبت شده، بدون نام خانوادگی بفرست.",
        reply_keyboard([BACK_STEP, CANCEL]),
        placeholder="مثلاً آرین",
    )


def last_name_screen() -> WizardScreen:
    return WizardScreen(
        f"{_step_title('last-name', '👤', 'نام خانوادگی', azad=False)}\n\n"
        "📝 نام خانوادگی‌ات را کامل بفرست.",
        reply_keyboard([BACK_STEP, CANCEL]),
        placeholder="نام خانوادگی",
    )


@dataclass(frozen=True)
class ListPage:
    """One page of a directory listing step, plus this wizard's own page
    history (a stack of cursors) so "previous page" can go back through a
    server-paginated, cursor-based read -- DirectoryReadService has no
    "give me page N" call, only "give me the page after this cursor"."""

    items: list[dict]
    page_index: int
    has_previous: bool
    has_next: bool


def province_screen(page: ListPage) -> WizardScreen:
    names = [str(item["name"]) for item in page.items]
    rows = [names[i:i + 2] for i in range(0, len(names), 2)]
    nav = []
    if page.has_previous:
        nav.append(PREVIOUS_PAGE)
    if page.has_next:
        nav.append(NEXT_PAGE)
    if nav:
        rows.append(nav)
    rows.append([BACK_STEP, CANCEL])
    return WizardScreen(
        f"{_step_title('province', '📍', 'استان دانشگاه', azad=False)}\n\n"
        "🗺 استان محل دانشگاهت را انتخاب کن.\n"
        f"<blockquote>صفحه {to_fa_digits(str(page.page_index + 1))}</blockquote>",
        reply_keyboard(*rows),
    )


def institution_screen(page: ListPage, province_name: str) -> WizardScreen:
    names = [str(item["name"]) for item in page.items]
    rows = [[name] for name in names]
    nav = []
    if page.has_previous:
        nav.append(PREVIOUS_PAGE)
    if page.has_next:
        nav.append(NEXT_PAGE)
    if nav:
        rows.append(nav)
    rows.append([BACK_STEP, CANCEL])
    return WizardScreen(
        f"{_step_title('institution', '🏛', 'دانشگاه یا دانشکده', azad=False)}\n\n"
        f"🏫 مرکز محل تحصیل در استان <b>{html.escape(province_name)}</b> را انتخاب کن.\n"
        "<blockquote>دانشگاه‌های علوم پزشکی دولتی و واحدهای علوم پزشکی دانشگاه آزاد در همین فهرست‌اند.</blockquote>",
        reply_keyboard(*rows),
    )


def faculty_screen(page: ListPage, institution_name: str, *, azad: bool) -> WizardScreen:
    names = [str(item["name"]) for item in page.items]
    rows = [[name] for name in names]
    nav = []
    if page.has_previous:
        nav.append(PREVIOUS_PAGE)
    if page.has_next:
        nav.append(NEXT_PAGE)
    if nav:
        rows.append(nav)
    rows.append([BACK_STEP, CANCEL])
    return WizardScreen(
        f"{_step_title('faculty', '🏢', 'دانشکده', azad=azad)}\n\n"
        f"🏢 دانشکده یا گروه آموزشی خودت را در <b>{html.escape(institution_name)}</b> انتخاب کن.",
        reply_keyboard(*rows),
    )


def program_screen(page: ListPage, faculty_name: str, *, azad: bool) -> WizardScreen:
    names = [str(item["name"]) for item in page.items]
    rows = [[name] for name in names]
    nav = []
    if page.has_previous:
        nav.append(PREVIOUS_PAGE)
    if page.has_next:
        nav.append(NEXT_PAGE)
    if nav:
        rows.append(nav)
    rows.append([BACK_STEP, CANCEL])
    return WizardScreen(
        f"{_step_title('program', '🎓', 'رشته تحصیلی', azad=azad)}\n\n"
        f"📚 رشته‌ات را در <b>{html.escape(faculty_name)}</b> انتخاب کن.",
        reply_keyboard(*rows),
    )


def entry_year_screen(*, azad: bool) -> WizardScreen:
    rows = [list(ENTRY_YEARS[i:i + 4]) for i in range(0, len(ENTRY_YEARS), 4)]
    rows.append([BACK_STEP, CANCEL])
    return WizardScreen(
        f"{_step_title('entry-year', '📅', 'سال ورود', azad=azad)}\n\n"
        "🎓 سال ورودت به دانشگاه را انتخاب کن.",
        reply_keyboard(*rows),
    )


def entry_term_screen(*, azad: bool) -> WizardScreen:
    explanation = (
        "🗓 برای دانشگاه آزاد، نوع پذیرش فقط با نیمسال اول یا دوم ثبت می‌شود."
        if azad else
        "🗓 نیمسال ورودی‌ات را انتخاب کن."
    )
    return WizardScreen(
        f"{_step_title('entry-term', '🗓', 'نیمسال ورودی', azad=azad)}\n\n{explanation}",
        reply_keyboard(list(ENTRY_TERMS), [BACK_STEP, CANCEL]),
    )


def course_type_screen() -> WizardScreen:
    return WizardScreen(
        f"{_step_title('course-type', '🏷', 'نوع دوره', azad=False)}\n\n🏷 نوع پذیرشت را انتخاب کن.",
        reply_keyboard(*[[item] for item in COURSE_TYPES], [BACK_STEP, CANCEL]),
    )


def student_number_screen(*, azad: bool) -> WizardScreen:
    return WizardScreen(
        f"{_step_title('student-number', '🪪', 'شماره دانشجویی', azad=azad)}\n\n"
        "🪪 اگر شماره دانشجویی داری آن را بفرست؛ این مرحله اختیاری است.",
        reply_keyboard([SKIP_STUDENT_NUMBER], [BACK_STEP, CANCEL]),
        placeholder="شماره دانشجویی",
    )


def review_screen(answers: dict, *, azad: bool) -> WizardScreen:
    student_number = html.escape(str(answers.get("student_number") or "ثبت نشده"))
    admission = str(answers.get("entry_term") or "")
    if not azad and answers.get("course_type"):
        admission = f"{admission} ({answers.get('course_type')})"
    text = (
        f"{_step_title('review', '✅', 'بررسی نهایی اطلاعات', azad=azad)}\n\n"
        f"نام: <b>{html.escape(str(answers.get('first_name') or ''))} {html.escape(str(answers.get('last_name') or ''))}</b>\n"
        f"دانشگاه: {html.escape(str(answers.get('institution_name') or ''))}\n"
        f"دانشکده: {html.escape(str(answers.get('faculty_name') or ''))}\n"
        f"رشته: {html.escape(str(answers.get('program_name') or ''))}\n"
        f"سال ورود: {html.escape(str(answers.get('entry_year_fa') or ''))}\n"
        f"نیمسال/نوع پذیرش: {html.escape(admission)}\n"
        f"شماره دانشجویی: <code>{student_number}</code>\n\n"
        "<blockquote>شماره موبایل در مرحله بعد و به‌عنوان آخرین داده دریافت می‌شود.</blockquote>"
    )
    return WizardScreen(text, reply_keyboard([CONFIRM_PROFILE], [BACK_STEP, CANCEL], [RESTART_PROFILE]))


def contact_screen() -> WizardScreen:
    return WizardScreen(
        f"{_step_title('contact', '📱', 'شماره موبایل', azad=False)}\n\n"
        "برای تأیید مالکیت شماره، فقط دکمه زیر را بزن و Contact خودت را ارسال کن.\n\n"
        "<blockquote>🔒 شمارهٔ شما کاملاً محفوظ است و نزد ما می‌ماند؛ فقط برای جلوگیری از ورود هوش مصنوعی و ربات‌ها و برای احراز هویت واقعی شما استفاده می‌شود.</blockquote>",
        reply_keyboard([{"text": SHARE_CONTACT, "request_contact": True}], [BACK_STEP, CANCEL]),
    )


def otp_screen(phone_masked: str, *, error: str = "") -> WizardScreen:
    notice = f"\n\n⚠️ {html.escape(error)}" if error else ""
    return WizardScreen(
        f"{_step_title('otp', '🔐', 'کد تأیید', azad=False)}\n\n"
        f"کد پیامک‌شده به <code>{html.escape(phone_masked)}</code> را بفرست.{notice}\n"
        "<blockquote>کد کوتاه‌عمر و یک‌بارمصرف است و در ربات ذخیره نمی‌شود.</blockquote>",
        reply_keyboard([RESEND_OTP], [CHANGE_PHONE], [BACK_STEP, CANCEL]),
        placeholder="کد ۶ رقمی",
    )


def success_notice(workspace_name: str) -> str:
    """Plain text (no HTML) meant for a normal ui_v3 screen's `notice`
    parameter, since the handoff after the wizard ends goes through the
    regular pipeline, not the reply-keyboard one."""
    return (
        f"✅ عضویت تو در «{workspace_name}» ثبت شد. "
        "می‌تونی از خدماتی مثل خرید و اطلاعیه‌ها استفاده کنی؛ برای دسترسی به برنامه کلاسی، نمرات و آزمون‌ها باید نماینده کلاست تأییدت کنه."
    )


def empty_list_screen(message: str) -> WizardScreen:
    """No options exist at this directory level yet (e.g. the chosen
    institution has zero faculties provisioned) -- distinct from
    class_not_found_screen: there is no program_id here to attach a creation
    request to, so this only offers going back to try a different choice."""
    return WizardScreen(
        f"<b><u>😕 موردی پیدا نشد</u></b>\n\n{html.escape(message)}",
        reply_keyboard([BACK_STEP, CANCEL]),
    )


def class_not_found_screen() -> WizardScreen:
    return WizardScreen(
        "<b><u>😕 کلاسی پیدا نشد</u></b>\n\n"
        "برای این رشته و سال ورود هنوز کلاسی روی فانوس ساخته نشده.\n\n"
        "می‌تونی درخواست بدی تا کلاست ساخته بشه و بعد از اون با نماینده‌ات در ارتباط باشی.",
        reply_keyboard([REQUEST_CLASS_CREATION], [CANCEL]),
    )


def class_creation_recorded_notice() -> str:
    return "📝 درخواست ساخت این کلاس ثبت شد. وقتی کلاست روی فانوس ساخته بشه، می‌تونی دوباره وارد بشی."


@dataclass(frozen=True)
class RawKeyboardSend:
    """Tells the runtime to send this reply-keyboard screen directly via
    botapi (send_reply_keyboard), bypassing the ui_v3 Screen/deliver()
    pipeline entirely -- that pipeline only ever emits inline keyboards, so
    it cannot represent a reply keyboard at all."""

    screen: WizardScreen


@dataclass(frozen=True)
class RawKeyboardHandoff:
    """The wizard is ending (success, class-not-found+recorded, or cancel):
    clear the reply keyboard, then let the runtime continue with a normal
    ui_v3 `handoff` result -- e.g. the home screen, whose own `notice`
    parameter already carries any one-time transition message. `handoff` is
    an ActionResult, kept as Any here to avoid join_wizard.py depending on
    application.py's assembly of one."""

    handoff: Any = None
