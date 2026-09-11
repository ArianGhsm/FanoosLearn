from __future__ import annotations

import html

from .persian_datetime import to_persian_digits
from .ui import Screen


START_GENERIC = "✍️ وارد کردن نام و نام خانوادگی"
START_CLASS = "👥 دانشجوی ورودی ۱۴۰۲ دندانپزشکی تهران هستم"
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
CLASS_OTP = "🔐 ورود با کد یک‌بارمصرف سایت"
CLASS_SITE = "🌐 ورود با نام کاربری و رمز سایت"


_STEP_POSITION = {
    "first-name": 1,
    "last-name": 2,
    "major": 3,
    "province": 4,
    "institution": 5,
    "entry-year": 6,
    "entry-term": 7,
    "course-type": 8,
    "student-number": 9,
    "review": 10,
    "contact": 11,
    "otp": 12,
}


def _step_title(step: str, icon: str, title: str, payload: dict | None = None) -> str:
    position = _STEP_POSITION.get(step, 1)
    total_steps = len(_STEP_POSITION)
    values = dict(payload or {})
    profile = values.get("profile") if isinstance(values.get("profile"), dict) else {}
    institution_system = str(values.get("institutionSystem") or profile.get("institutionSystem") or "")
    if institution_system == "azad":
        total_steps -= 1
        if position > _STEP_POSITION["course-type"]:
            position -= 1
    current = to_persian_digits(position)
    total = to_persian_digits(total_steps)
    return f"<b><u>{icon} {html.escape(title)}</u></b>\n<code>مرحله {current} از {total}</code>"


def reply_keyboard(*rows: list[dict | str], placeholder: str = "") -> dict:
    keyboard_rows: list[list[dict]] = []
    for row in rows:
        keyboard_rows.append([item if isinstance(item, dict) else {"text": item} for item in row])
    result: dict = {"keyboard": keyboard_rows, "resize_keyboard": True, "one_time_keyboard": False}
    if placeholder:
        result["input_field_placeholder"] = placeholder[:64]
    return result


def gateway_screen() -> Screen:
    return Screen(
        "<b><u>👋 خوش آمدی به دنت‌یار</u></b>\n\n"
        "<b>🔹 ۱. عضو ورودی ۱۴۰۲ هستی؟</b>\n"
        "مسیر اختصاصی کلاس و اتصال امن حساب سایت را انتخاب کن.\n\n"
        "<b>🔹 ۲. دانشجوی دانشگاه دیگری هستی؟</b>\n"
        "ثبت مشخصات عمومی را شروع کن.\n\n"
        "<blockquote>این مشخصات در تلگرام و بله یکسان می‌ماند.</blockquote>",
        reply_keyboard(
            [START_GENERIC],
            [START_CLASS],
            placeholder="یکی از دو مسیر را انتخاب کن",
        ),
    )


def prompt_screen(step: str, payload: dict, catalog: dict) -> Screen:
    if step == "first-name":
        return Screen(
            f"{_step_title(step, '👤', 'نام', payload)}\n\n"
            "✨ نامت را همان‌طور که در دانشگاه ثبت شده، بدون نام خانوادگی بفرست.",
            reply_keyboard([BACK_STEP, CANCEL], placeholder="مثلاً آرین"),
        )
    if step == "last-name":
        return Screen(
            f"{_step_title(step, '👤', 'نام خانوادگی', payload)}\n\n"
            "📝 نام خانوادگی‌ات را کامل بفرست.",
            reply_keyboard([BACK_STEP, CANCEL], placeholder="نام خانوادگی"),
        )
    if step == "major":
        majors = [str(item) for item in catalog.get("majors", [])]
        return Screen(
            f"{_step_title(step, '🎓', 'رشته تحصیلی', payload)}\n\n📚 رشته‌ات را از فهرست انتخاب کن.",
            reply_keyboard(*[[item] for item in majors], [BACK_STEP, CANCEL]),
        )
    if step == "province":
        provinces = [str(item) for item in catalog.get("provinces", [])]
        page_size = 10
        page_count = max(1, (len(provinces) + page_size - 1) // page_size)
        page = max(0, min(int(payload.get("provincePage") or 0), page_count - 1))
        choices = provinces[page * page_size:(page + 1) * page_size]
        rows = [choices[index:index + 2] for index in range(0, len(choices), 2)]
        nav = []
        if page > 0:
            nav.append(PREVIOUS_PAGE)
        if page + 1 < page_count:
            nav.append(NEXT_PAGE)
        if nav:
            rows.append(nav)
        rows.append([BACK_STEP, CANCEL])
        return Screen(
            f"{_step_title(step, '📍', 'استان دانشگاه', payload)}\n\n🗺 استان محل دانشگاهت را انتخاب کن.\n"
            f"<blockquote>صفحه {to_persian_digits(page + 1)} از {to_persian_digits(page_count)}</blockquote>",
            reply_keyboard(*rows),
        )
    if step == "institution":
        province = str(payload.get("province") or "")
        institutions = [
            str(item.get("name") or "")
            for item in catalog.get("institutions", [])
            if isinstance(item, dict) and str(item.get("province") or "") == province
        ]
        return Screen(
            f"{_step_title(step, '🏛', 'دانشگاه یا دانشکده', payload)}\n\n"
            f"🏫 مرکز محل تحصیل در استان <b>{html.escape(province)}</b> را انتخاب کن.\n"
            "<blockquote>دانشگاه‌های علوم پزشکی دولتی و واحدهای علوم پزشکی دانشگاه آزاد در همین فهرست‌اند.</blockquote>",
            reply_keyboard(*[[item] for item in institutions], [BACK_STEP, CANCEL]),
        )
    if step == "entry-year":
        choices = [str(item) for item in catalog.get("entryYears", [])]
        rows = [choices[index:index + 4] for index in range(0, len(choices), 4)]
        rows.append([BACK_STEP, CANCEL])
        return Screen(
            f"{_step_title(step, '📅', 'سال ورود', payload)}\n\n🎓 سال ورودت به دانشگاه را انتخاب کن.",
            reply_keyboard(*rows),
        )
    if step == "entry-term":
        choices = [str(item) for item in catalog.get("entryTerms", [])]
        is_azad = str(payload.get("institutionSystem") or "") == "azad"
        explanation = (
            "🗓 برای دانشگاه آزاد، نوع پذیرش فقط با نیمسال اول یا دوم ثبت می‌شود."
            if is_azad else
            "🗓 نیمسال ورودی‌ات را انتخاب کن."
        )
        return Screen(
            f"{_step_title(step, '🗓', 'نیمسال ورودی', payload)}\n\n{explanation}",
            reply_keyboard([*choices], [BACK_STEP, CANCEL]),
        )
    if step == "course-type":
        choices = [str(item) for item in catalog.get("courseTypes", [])]
        return Screen(
            f"{_step_title(step, '🏷', 'نوع دوره', payload)}\n\n🏷 نوع پذیرشت را انتخاب کن.",
            reply_keyboard(*[[item] for item in choices], [BACK_STEP, CANCEL]),
        )
    if step == "student-number":
        return Screen(
            f"{_step_title(step, '🪪', 'شماره دانشجویی', payload)}\n\n"
            "🪪 اگر شماره دانشجویی داری آن را بفرست؛ این مرحله اختیاری است.",
            reply_keyboard([SKIP_STUDENT_NUMBER], [BACK_STEP, CANCEL], placeholder="شماره دانشجویی"),
        )
    if step == "review":
        student_number = html.escape(str(payload.get("studentNumber") or "ثبت نشده"))
        text = (
            f"{_step_title(step, '✅', 'بررسی نهایی اطلاعات', payload)}\n\n"
            f"نام: <b>{html.escape(str(payload.get('firstName') or ''))} {html.escape(str(payload.get('lastName') or ''))}</b>\n"
            f"رشته: {html.escape(str(payload.get('major') or ''))}\n"
            f"دانشگاه: {html.escape(str(payload.get('institution') or ''))}\n"
            f"سال ورود: {html.escape(str(payload.get('entryYear') or ''))}\n"
            f"نوع پذیرش: {html.escape(str(payload.get('admissionType') or ''))}\n"
            f"شماره دانشجویی: <code>{student_number}</code>\n\n"
            "<blockquote>شماره موبایل در مرحله بعد و به‌عنوان آخرین داده دریافت می‌شود.</blockquote>"
        )
        return Screen(text, reply_keyboard([CONFIRM_PROFILE], [BACK_STEP, CANCEL], [RESTART_PROFILE]))
    if step == "contact":
        return Screen(
            f"{_step_title(step, '📱', 'شماره موبایل', payload)}\n\n"
            "برای تأیید مالکیت شماره، فقط دکمه زیر را بزن و Contact خودت را ارسال کن.\n\n"
            "<blockquote>🔒 شمارهٔ شما کاملاً محفوظ است و نزد ما می‌ماند؛ فقط برای جلوگیری از ورود هوش مصنوعی و ربات‌ها و برای احراز هویت واقعی شما استفاده می‌شود.</blockquote>",
            reply_keyboard([{"text": SHARE_CONTACT, "request_contact": True}], [BACK_STEP, CANCEL]),
        )
    if step == "otp":
        masked = html.escape(str(payload.get("phoneMasked") or "شماره شما"))
        return Screen(
            f"{_step_title(step, '🔐', 'کد تأیید', payload)}\n\n"
            f"کد پیامک‌شده به <code>{masked}</code> را بفرست.\n"
            "<blockquote>کد کوتاه‌عمر و یک‌بارمصرف است و در ربات ذخیره نمی‌شود.</blockquote>",
            reply_keyboard([RESEND_OTP], [CHANGE_PHONE], [BACK_STEP, CANCEL], placeholder="کد ۶ رقمی"),
        )
    return gateway_screen()


def success_screen(profile: dict) -> Screen:
    student_number = html.escape(str(profile.get("studentNumber") or "ثبت نشده"))
    return Screen(
        "<b><u>✅ مشخصات تأیید و ذخیره شد</u></b>\n\n"
        f"{html.escape(str(profile.get('firstName') or ''))} {html.escape(str(profile.get('lastName') or ''))}\n"
        f"{html.escape(str(profile.get('major') or ''))} · {html.escape(str(profile.get('institution') or ''))}\n"
        f"ورودی {html.escape(str(profile.get('entryYear') or 'ثبت نشده'))}\n"
        f"{html.escape(str(profile.get('admissionType') or ''))}\n"
        f"شماره دانشجویی: <code>{student_number}</code>\n"
        f"موبایل: <code>{html.escape(str(profile.get('phoneMasked') or ''))}</code>\n\n"
        "<blockquote>همین پروفایل پس از تأیید همان شماره در ربات دیگر نیز همگام می‌شود. منوی عمومی باز است؛ اطلاعات خصوصی همچنان به اتصال حساب سایت نیاز دارد.</blockquote>",
        {
            "inline_keyboard": [[{
                "text": "🏠 ورود به دنت‌یار",
                "callback_data": "v1:home",
                "style": "success",
            }]],
        },
    )


def class_auth_screen() -> Screen:
    return Screen(
        "<b><u>🪪 احراز هویت ورودی ۱۴۰۲</u></b>\n\n"
        "<b>🔹 ۱. کد یک‌بارمصرف سایت</b>\n"
        "کد به همان شمارهٔ تأییدشدهٔ حساب سایت ارسال می‌شود.\n\n"
        "<b>🔹 ۲. ورود امن در سایت</b>\n"
        "صفحهٔ سایت باز می‌شود؛ وارد شو، اتصال را تأیید کن و بعد به ربات برگرد.\n\n"
        "<blockquote>تأیید دستی و تطبیق نام غیرفعال است.</blockquote>",
        reply_keyboard([CLASS_OTP], [CLASS_SITE], [BACK_STEP, CANCEL]),
    )


def class_student_number_screen() -> Screen:
    return Screen(
        "<b><u>🪪 شماره دانشجویی حساب سایت</u></b>\n\n"
        "شماره دانشجویی خودت را بفرست تا کد به شمارهٔ OTP همان حساب ارسال شود.",
        reply_keyboard([BACK_STEP, CANCEL], placeholder="شماره دانشجویی"),
    )


def class_otp_screen(masked_phone: str) -> Screen:
    return Screen(
        f"<b><u>🔐 کد ورود سایت</u></b>\n\n"
        f"کد ۶ رقمی ارسال‌شده به <code>{html.escape(masked_phone)}</code> را بفرست.\n"
        "<blockquote>کد در ربات ذخیره نمی‌شود و فقط همین اتصال را تأیید می‌کند.</blockquote>",
        reply_keyboard([BACK_STEP, CANCEL], placeholder="کد ۶ رقمی"),
    )
