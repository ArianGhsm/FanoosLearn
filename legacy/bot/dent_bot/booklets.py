from __future__ import annotations

import html
import re
from dataclasses import dataclass

from .persian_datetime import to_persian_digits
from .ui import Screen


BOOKLET_BACK = "↩️ مرحله قبل"
BOOKLET_CANCEL = "انصراف"
BOOKLET_HOME = "🏠 منوی اصلی"

RESOURCE_LABELS = {
    "voice": "🎤 ویس",
    "power": "📒 پاور",
    "booklet": "📓 جزوه",
    "reference": "📘 رفرنس",
}

COURSES = (
    {"code": "PeriodontologyTheory1", "name": "پریودنتولوژی نظری ۱", "tag": "پریو_نظری۱", "term": 7},
    {"code": "DiagnosticDentistry3", "name": "دندانپزشکی تشخیصی ۳", "tag": "تشخیصی۳", "term": 7},
    {"code": "ENT", "name": "گوش و حلق و بینی", "tag": "گوش_حلق_بینی", "term": 7},
    {"code": "OrthodonticsTheory1", "name": "ارتودانتیکس نظری ۱", "tag": "ارتو_نظری۱", "term": 7},
    {"code": "PartialProsthodonticsFoundations", "name": "مبانی پروتز پارسیل نظری", "tag": "مبانی_پروتز_پارسیل", "term": 7},
    {"code": "EndodonticsTheory1", "name": "اندودانتیکس نظری ۱", "tag": "اندو_نظری۱", "term": 7},
    {"code": "OralHealthTheory2", "name": "سلامت دهان نظری ۲", "tag": "سلامت_دهان_نظری۲", "term": 7},
    {"code": "EndodonticsFoundations2", "name": "مبانی اندودانتیکس ۲", "tag": "مبانی_اندودانتیکس۲", "term": 7},
    {"code": "OralMedicinePractical1", "name": "بیماری‌های دهان عملی ۱", "tag": "بیماری_دهان_عملی۱", "term": 7},
    {"code": "OperativeDentistryPractical2", "name": "ترمیمی عملی ۲", "tag": "ترمیمی_عملی۲", "term": 7},
    {"code": "OralHealthPractical2", "name": "سلامت دهان عملی ۲", "tag": "سلامت_دهان_عملی۲", "term": 7},
    {"code": "PartialProsthodonticsPractical1", "name": "پروتز پارسیل عملی ۱", "tag": "پروتز_پارسیل_عملی۱", "term": 7},
    {"code": "PathologyPractical1", "name": "آسیب‌شناسی عملی ۱", "tag": "آسیب_شناسی_عملی۱", "term": 7},
    {"code": "SurgeryPractical2", "name": "جراحی عملی ۲", "tag": "جراحی_عملی۲", "term": 7},
    {"code": "ResearchMethodology2", "name": "روش‌شناسی تحقیق ۲", "tag": "روش_تحقیق۲", "term": 7},
)

ENT_SESSIONS = (
    (1, "سینوزیت", "طبری"),
    (2, "معاینه و اصول", "گل‌پروران"),
    (3, "آنومالی‌های گوش و حلق و بینی", "میراشرفی"),
    (4, "تومورهای سینوس", "ایرانی"),
    (5, "اپیستاکسی", "موسوی"),
    (6, "آبسه‌های گردنی و عمقی صورت", "عرفانیان"),
    (7, "حنجره و تراکوستومی", "امیرزرگر"),
    (8, "درد صورت", "فیروزی‌فر"),
    (9, "بیماری‌های التهابی سینوس و بینی", "حیدری"),
    (10, "بیماری‌های حفرهٔ دهان", "محبی"),
    (12, "حنجره و راه‌های هوایی", "سعیدی"),
    (13, "تروماهای سر و گردن", "علیپور"),
)

COURSE_BY_CODE = {str(item["code"]): item for item in COURSES}
COURSE_BY_NAME = {str(item["name"]): item for item in COURSES}
COURSE_BY_TAG = {str(item["tag"]): item for item in COURSES}
SESSIONS_BY_COURSE = {"ENT": ENT_SESSIONS}

_ORDINALS = {
    1: "اول", 2: "دوم", 3: "سوم", 4: "چهارم", 5: "پنجم", 6: "ششم", 7: "هفتم",
    8: "هشتم", 9: "نهم", 10: "دهم", 11: "یازدهم", 12: "دوازدهم", 13: "سیزدهم",
    14: "چهاردهم", 15: "پانزدهم", 16: "شانزدهم", 17: "هفدهم", 18: "هجدهم",
    19: "نوزدهم", 20: "بیستم", 21: "بیست و یکم", 22: "بیست و دوم",
    23: "بیست و سوم", 24: "بیست و چهارم", 25: "بیست و پنجم", 26: "بیست و ششم",
    27: "بیست و هفتم", 28: "بیست و هشتم", 29: "بیست و نهم", 30: "سی‌ام",
    31: "سی و یکم", 32: "سی و دوم", 33: "سی و سوم", 34: "سی و چهارم",
    35: "سی و پنجم", 36: "سی و ششم", 37: "سی و هفتم", 38: "سی و هشتم",
    39: "سی و نهم", 40: "چهلم",
}


def _normalized(value: str) -> str:
    return " ".join(
        str(value)
        .replace("ي", "ی")
        .replace("ى", "ی")
        .replace("ك", "ک")
        .replace("‌", " ")
        .split()
    )


_ORDINAL_LOOKUP = {_normalized(word): number for number, word in _ORDINALS.items()}
_ORDINAL_PATTERN = "|".join(
    re.escape(value) for value in sorted(_ORDINAL_LOOKUP, key=len, reverse=True)
)
_DIGIT_TRANSLATION = str.maketrans("۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩", "01234567890123456789")


def ordinal(number: int) -> str:
    return _ORDINALS.get(int(number), to_persian_digits(number))


def parse_session_number(caption: str) -> int | None:
    normalized = _normalized(caption).translate(_DIGIT_TRANSLATION)
    match = re.search(rf"(?:^|\s)جلسه\s+(?P<value>{_ORDINAL_PATTERN}|[0-9]{{1,2}})(?:\s|$|[-–—:])", normalized)
    if not match:
        return None
    value = match.group("value")
    number = int(value) if value.isdigit() else _ORDINAL_LOOKUP.get(value, 0)
    return number if 1 <= number <= 40 else None


def _hashtags(caption: str) -> set[str]:
    return {match.group(1).replace("‌", "_") for match in re.finditer(r"#([^\s#]+)", caption)}


def _content_kinds(caption: str) -> tuple[str, ...]:
    normalized = _normalized(caption)
    kinds: list[str] = []
    for kind, tokens in (
        ("voice", ("ویس",)),
        ("power", ("پاور", "پاورپوینت")),
        ("booklet", ("جزوه",)),
        ("reference", ("رفرنس",)),
    ):
        if any(re.search(rf"(?<!\w){re.escape(token)}(?!\w)", normalized) for token in tokens):
            kinds.append(kind)
    return tuple(kinds)


@dataclass(frozen=True)
class ParsedSource:
    course_code: str
    course_name: str
    course_tag: str
    term: int
    session_no: int
    kinds: tuple[str, ...]


def parse_source_caption(caption: str) -> ParsedSource | None:
    tags = _hashtags(caption)
    course = next((COURSE_BY_TAG[tag] for tag in tags if tag in COURSE_BY_TAG), None)
    term = 0
    for tag in tags:
        match = re.fullmatch(r"ترم([0-9۰-۹٠-٩]{1,2})", tag)
        if match:
            term = int(match.group(1).translate(_DIGIT_TRANSLATION))
            break
    session_no = parse_session_number(caption) or 0
    kinds = _content_kinds(caption)
    if not course or not 1 <= term <= 12 or not 1 <= session_no <= 40 or not kinds:
        return None
    return ParsedSource(
        course_code=str(course["code"]),
        course_name=str(course["name"]),
        course_tag=str(course["tag"]),
        term=term,
        session_no=session_no,
        kinds=kinds,
    )


def source_records_from_channel_post(message: dict) -> list[dict]:
    caption = str(message.get("caption") or message.get("text") or "")
    parsed = parse_source_caption(caption)
    if parsed is None:
        return []
    media_type = ""
    media: dict = {}
    for candidate in ("document", "audio", "voice"):
        if isinstance(message.get(candidate), dict):
            media_type = candidate
            media = dict(message[candidate])
            break
    if not media_type:
        return []
    method = {"document": "sendDocument", "audio": "sendAudio", "voice": "sendVoice"}[media_type]
    return [{
        "courseCode": parsed.course_code,
        "courseName": parsed.course_name,
        "courseTag": parsed.course_tag,
        "term": parsed.term,
        "sessionNo": parsed.session_no,
        "contentKind": kind,
        "telegramMethod": method,
        "fileId": str(media.get("file_id") or ""),
        "fileUniqueId": str(media.get("file_unique_id") or ""),
        "fileName": str(media.get("file_name") or "")[:240],
        "mimeType": str(media.get("mime_type") or "")[:120],
        "caption": caption[:3000],
    } for kind in parsed.kinds]


def _reply_keyboard(*rows: list[str]) -> dict:
    return {
        "keyboard": [[{"text": item} for item in row] for row in rows],
        "resize_keyboard": True,
        "one_time_keyboard": False,
    }


def course_button(course: dict) -> str:
    return f"📚 {course['name']}"


def course_from_button(text: str) -> dict | None:
    name = text.removeprefix("📚 ").strip()
    return COURSE_BY_NAME.get(name)


def courses_screen() -> Screen:
    rows = [[course_button(course)] for course in COURSES]
    rows.append([BOOKLET_HOME, BOOKLET_CANCEL])
    return Screen(
        "<b><u>📚 آرشیو امن جزوات</u></b>\n\n"
        "درس را انتخاب کن تا جلسات همان طرح درس نمایش داده شود.\n"
        "<blockquote>فایل‌ها فقط برای کاربر احرازهویت‌شده و با محافظت تلگرام ارسال می‌شوند.</blockquote>",
        _reply_keyboard(*rows),
    )


def session_button(session: tuple[int, str, str]) -> str:
    number, topic, _teacher = session
    return f"جلسه {ordinal(number)} — {topic}"


def session_from_button(course_code: str, text: str) -> tuple[int, str, str] | None:
    return next(
        (session for session in SESSIONS_BY_COURSE.get(course_code, ()) if session_button(session) == text),
        None,
    )


def sessions_screen(course_code: str) -> Screen:
    course = COURSE_BY_CODE[course_code]
    sessions = SESSIONS_BY_COURSE.get(course_code, ())
    rows = [[session_button(session)] for session in sessions]
    rows.append([BOOKLET_BACK, BOOKLET_CANCEL])
    if not sessions:
        body = "طرح درس این واحد هنوز وارد نشده است؛ جلسه‌ای حدس زده یا ساخته نمی‌شود."
    else:
        body = "جلسه را از فهرست طرح درس انتخاب کن."
    return Screen(
        f"<b><u>📚 {html.escape(str(course['name']))}</u></b>\n\n{body}",
        _reply_keyboard(*rows),
    )


def resources_screen(course_code: str, session_no: int) -> Screen:
    course = COURSE_BY_CODE[course_code]
    session = next(item for item in SESSIONS_BY_COURSE[course_code] if item[0] == session_no)
    _number, topic, teacher = session
    return Screen(
        f"<b><u>جلسه {ordinal(session_no)} — {html.escape(topic)}</u></b>\n\n"
        f"📚 {html.escape(str(course['name']))}\n"
        f"👨‍🏫 استاد {html.escape(teacher)}\n\n"
        "نوع فایل را انتخاب کن:",
        _reply_keyboard(
            [RESOURCE_LABELS["voice"], RESOURCE_LABELS["power"]],
            [RESOURCE_LABELS["booklet"], RESOURCE_LABELS["reference"]],
            [BOOKLET_BACK, BOOKLET_CANCEL],
        ),
    )


def bale_unavailable_screen() -> Screen:
    return Screen(
        "<b><u>📚 آرشیو امن جزوات</u></b>\n\n"
        "منبع فایل‌ها کانال خصوصی تلگرام است و شناسهٔ فایل آن در بله قابل استفاده نیست.\n"
        "<blockquote>این تفاوت فنی فقط در انتقال فایل است؛ احراز هویت و مجوزها مشترک می‌مانند.</blockquote>",
        {"inline_keyboard": [[{"text": "🏠 منوی اصلی", "callback_data": "v1:home"}]]},
    )
