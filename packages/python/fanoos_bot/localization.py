from __future__ import annotations

PLATFORM_LABELS = {
    "telegram": "تلگرام",
    "bale": "بله",
}

ORDER_STATUS_LABELS = {
    "pending": "در انتظار پرداخت",
    "created": "در انتظار پرداخت",
    "payment_pending": "در انتظار پرداخت",
    "paid": "پرداخت تأیید شده",
    "succeeded": "پرداخت تأیید شده",
    "failed": "ناموفق",
    "cancelled": "لغوشده",
    "canceled": "لغوشده",
    "expired": "منقضی",
    "refunded": "بازپرداخت‌شده",
}

DEPLOYMENT_STATE_LABELS = {
    "REQUESTED": "درخواست ثبت شده",
    "PREFLIGHT": "بررسی پیش‌نیازها",
    "BACKUP": "پشتیبان‌گیری",
    "TESTING": "اجرای آزمون‌ها",
    "MIGRATING": "آماده‌سازی تغییرات داده",
    "ACTIVATING": "فعال‌سازی نسخه",
    "RESTARTING": "راه‌اندازی مجدد سرویس‌ها",
    "HEALTHCHECK": "بررسی سلامت",
    "SUCCEEDED": "با موفقیت انجام شد",
    "FAILED": "ناموفق",
    "ROLLED_BACK": "بازگشت به نسخه قبلی انجام شد",
}

HEALTH_STATUS_LABELS = {
    "healthy": "سالم",
    "ok": "سالم",
    "ready": "آماده",
    "degraded": "نیازمند بررسی",
    "unhealthy": "ناسالم",
    "unknown": "نامشخص",
}

RESOURCE_TYPE_LABELS = {
    "pdf": "PDF",
    "document": "فایل آموزشی",
    "file": "فایل آموزشی",
    "booklet": "جزوه",
    "lecture_note": "جزوه",
    "note": "جزوه",
    "notes": "جزوه",
    "summary": "خلاصه",
    "dentnote": "DentNote",
    "discipline_note": "یادداشت تخصصی",
    "question_bank": "بانک سؤال",
    "past_exam": "آزمون گذشته",
    "past_questions": "سؤالات آزمون گذشته",
    "flashcard": "فلش‌کارت",
    "video": "فایل ویدیویی",
    "audio": "فایل صوتی",
    "image": "تصویر",
    "link": "پیوند",
    "quiz": "آزمون کوتاه",
}

ERROR_MESSAGES = {
    "messaging_link_required": "اتصال پیام‌رسان لازم است. از فانوس یک کد اتصال تازه بگیرید و دوباره امتحان کنید.",
    "messaging_link_not_found": "اتصال فعالی برای این پیام‌رسان پیدا نشد.",
    "workspace_required": "ابتدا یک فضای آموزشی فعال انتخاب کنید.",
    "workspace_forbidden": "این فضای آموزشی برای حساب شما در دسترس نیست. یک فضای آموزشی دیگر را انتخاب کنید.",
    "workspace_not_found": "فضای آموزشی در دسترس نیست.",
    "workspace_timezone_invalid": "تنظیم زمان این فضای آموزشی معتبر نیست. از سایت فانوس پیگیری کنید.",
    "resource_access_denied": "دسترسی به این منبع دیگر فعال نیست. فهرست منابع را دوباره بررسی کنید.",
    "resource_not_found": "این منبع دیگر در دسترس نیست. فهرست منابع را دوباره بررسی کنید.",
    "content_unavailable": "این محتوا فعلاً در دسترس نیست.",
    "entitlement_inactive": "دسترسی این محتوا برای حساب شما فعال نیست.",
    "payment_pending": "پرداخت هنوز نهایی نشده است. وضعیت سفارش را دوباره بررسی کنید.",
    "delivery_token_expired": "درخواست دریافت منقضی شده است. دوباره از بخش منابع شروع کنید.",
    "delivery_token_unavailable": "درخواست دریافت دیگر معتبر نیست. دوباره از بخش منابع شروع کنید.",
    "delivery_token_invalid": "درخواست دریافت معتبر نیست. دوباره از بخش منابع شروع کنید.",
    "delivery_subject_mismatch": "این درخواست برای حساب یا فضای آموزشی دیگری است. از بخش منابع دوباره شروع کنید.",
    "protected_media_artifact_unavailable": "نسخه محافظت‌شده هنوز آماده نشده است.",
    "protected_media_job_not_found": "درخواست نسخه محافظت‌شده منقضی شده است. دوباره از بخش منابع شروع کنید.",
    "permission_denied": "اجازه انجام این عملیات را ندارید.",
    "forbidden": "اجازه انجام این عملیات را ندارید.",
    "service_scope_denied": "اجازه انجام این عملیات را ندارید.",
    "link_challenge_invalid": "درخواست اتصال نامعتبر است. از فانوس یک کد اتصال تازه بگیرید.",
    "link_challenge_expired": "درخواست اتصال منقضی شده است. از فانوس یک کد اتصال تازه بگیرید.",
    "link_challenge_used": "این درخواست اتصال قبلاً استفاده شده است. برای اتصال دوباره یک کد تازه بگیرید.",
    "platform_subject_conflict": "این پیام‌رسان از قبل به حساب دیگری متصل است.",
    "platform_already_linked": "برای این حساب یک اتصال فعال وجود دارد. ابتدا اتصال قبلی را قطع کنید.",
    "deployment_in_progress": "یک به‌روزرسانی دیگر در حال اجراست. وضعیت همان درخواست را بررسی کنید.",
    "cursor_invalid": "این صفحه دیگر معتبر نیست. فهرست را از ابتدا باز کنید.",
    "validation_error": "اطلاعات ورودی معتبر نیست.",
    "invalid_input": "اطلاعات ورودی معتبر نیست.",
    "rate_limited": "تعداد درخواست‌ها زیاد است. کمی بعد دوباره امتحان کنید.",
    "network_unavailable": "ارتباط با فانوس موقتاً برقرار نیست. دوباره امتحان کنید.",
    "service_unavailable": "سرویس موقتاً پاسخ نمی‌دهد. دوباره امتحان کنید.",
    "upstream_unavailable": "سرویس موقتاً پاسخ نمی‌دهد. دوباره امتحان کنید.",
    "timeout": "سرویس موقتاً پاسخ نمی‌دهد. دوباره امتحان کنید.",
}


def platform_label(value: object) -> str:
    return PLATFORM_LABELS.get(str(value or "").lower(), "پیام‌رسان")


def order_status_label(value: object) -> str:
    return ORDER_STATUS_LABELS.get(str(value or "").lower(), "در حال بررسی")


def entitlement_label(granted: object) -> str:
    if granted is True:
        return "فعال"
    if granted is False:
        return "فعال نیست"
    return "نامشخص"


def deployment_state_label(value: object) -> str:
    return DEPLOYMENT_STATE_LABELS.get(str(value or "").upper(), "وضعیت نامشخص")


def health_status_label(value: object) -> str:
    return HEALTH_STATUS_LABELS.get(str(value or "").lower(), "نامشخص")


def resource_type_label(value: object) -> str:
    raw = str(value or "").strip().lower()
    return RESOURCE_TYPE_LABELS.get(raw, "منبع")


def error_message(code: object, status: int | None = None) -> str:
    key = str(code or "").strip()
    if key in ERROR_MESSAGES:
        return ERROR_MESSAGES[key]
    if status is not None and status >= 500:
        return "سرویس موقتاً پاسخ نمی‌دهد. دوباره امتحان کنید."
    if status == 429:
        return "تعداد درخواست‌ها زیاد است. کمی بعد دوباره امتحان کنید."
    if status == 403:
        return "اجازه انجام این عملیات را ندارید."
    if status in {400, 404, 409, 410, 422}:
        return "درخواست منقضی یا نامعتبر است. اطلاعات را بررسی و دوباره امتحان کنید."
    return "این عملیات فعلاً قابل انجام نیست. دوباره امتحان کنید."
