from __future__ import annotations

from dataclasses import dataclass

from .bale import BaleV3Renderer
from .contract import (
    ProviderAction,
    ProviderContext,
    ProviderFact,
    ProviderScreen,
    ProviderSection,
)
from .telegram import TelegramV3Renderer


@dataclass(frozen=True)
class ProviderExample:
    key: str
    screen: ProviderScreen
    telegram_context: ProviderContext = ProviderContext(private_chat=True)
    bale_context: ProviderContext = ProviderContext(private_chat=True)


def _cb(label: str, callback: str, *, role: str = "secondary", **kwargs) -> ProviderAction:
    return ProviderAction(label=label, callback=callback, role=role, **kwargs)


def _url(label: str, url: str, *, role: str = "secondary", **kwargs) -> ProviderAction:
    return ProviderAction(label=label, url=url, role=role, **kwargs)


def representative_examples(web_origin: str = "https://fanoos.invalid") -> tuple[ProviderExample, ...]:
    web = web_origin.rstrip("/")
    home = _cb("🏠 خانه", "v3:home", role="home")
    back = _cb("بازگشت", "v3:back", role="back")

    return (
        ProviderExample(
            "unlinked",
            ProviderScreen(
                title="🏠 فانوس",
                semantic_kind="unlinked",
                intro="برای دیدن اطلاعات شخصی و آموزشی، ابتدا حساب فانوس را به این پیام‌رسان متصل کنید.",
                actions=(
                    _url("🔗 اتصال حساب", f"{web}/account", role="primary"),
                    _cb("ℹ️ راهنمای شروع", "v3:help"),
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "linked_no_workspace",
            ProviderScreen(
                title="🏠 فانوس",
                semantic_kind="linked_no_workspace",
                intro="حساب شما متصل است ✅\nهنوز فضای آموزشی فعالی برای این حساب ندارید.",
                actions=(
                    _cb("🏫 فضای آموزشی", "v3:workspaces", role="primary"),
                    _cb("👤 حساب من", "v3:account"),
                    _cb("ℹ️ راهنمای شروع", "v3:help"),
                    _url("🌐 باز کردن فانوس", web),
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "home_active",
            ProviderScreen(
                title="🏠 خانه",
                semantic_kind="home_active",
                context="دندان‌پزشکی تهران · ورودی ۱۴۰۲",
                sections=(
                    ProviderSection("📅 بعدی", "ترمیمی ۱ — ۰۸:۳۰\nدانشکده دندان‌پزشکی"),
                    ProviderSection("📢 تازه", "زمان آزمون میان‌ترم ترمیمی ۱ اعلام شد."),
                ),
                actions=(
                    _cb("📚 درس‌ها", "v3:courses"),
                    _cb("📅 برنامه", "v3:schedule"),
                    _cb("🎓 نمرات", "v3:grades"),
                    _cb("🔔 اعلان‌ها", "v3:notifications"),
                    _cb("📚 منابع", "v3:resources"),
                    _cb("📝 آزمون‌ها", "v3:assessments"),
                    _cb("👤 حساب", "v3:account"),
                    _cb("➕ بیشتر", "v3:more"),
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "courses",
            ProviderScreen(
                title="📚 درس‌ها",
                semantic_kind="courses",
                context="نیمسال جاری",
                list_items=(
                    "ترمیمی ۱ · REST-301",
                    "پریودانتیکس ۱ · PERIO-301",
                    "رادیولوژی ۱ · RAD-301",
                ),
                pagination="صفحه ۱ از ۲",
                actions=(
                    _cb("ترمیمی ۱", "v3:course:rest"),
                    _cb("پریودانتیکس ۱", "v3:course:perio"),
                    _cb("رادیولوژی ۱", "v3:course:radio"),
                    _cb("صفحه بعد", "v3:courses:next", role="pagination"),
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "course_detail",
            ProviderScreen(
                title="📚 ترمیمی ۱",
                semantic_kind="course_detail",
                context="درس‌ها › ترمیمی ۱",
                facts=(
                    ProviderFact("کد درس", "REST-301"),
                    ProviderFact("نیمسال", "جاری"),
                ),
                sections=(ProviderSection("قدم بعدی", "کلاس بعدی: شنبه، ۰۸:۳۰"),),
                actions=(
                    _cb("📅 برنامه", "v3:course:rest:schedule"),
                    _cb("📚 منابع", "v3:course:rest:resources"),
                    _cb("🎓 نمرات", "v3:course:rest:grades"),
                    _cb("📝 آزمون‌ها", "v3:course:rest:assessments"),
                    _cb("📢 اطلاعیه‌ها", "v3:course:rest:announcements"),
                    _cb("بازگشت به درس‌ها", "v3:courses", role="back"),
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "schedule",
            ProviderScreen(
                title="📅 برنامه امروز",
                semantic_kind="schedule",
                context="دندان‌پزشکی تهران · پنجشنبه ۱۹ شهریور",
                sections=(
                    ProviderSection("۰۸:۳۰", "ترمیمی ۱\nدانشکده دندان‌پزشکی"),
                    ProviderSection("۱۰:۳۰", "پریودانتیکس ۱\nکلینیک پریو"),
                ),
                actions=(
                    _cb("امروز", "v3:schedule:today"),
                    _cb("فردا", "v3:schedule:tomorrow"),
                    _cb("۷ روز آینده", "v3:schedule:week"),
                    back,
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "grades",
            ProviderScreen(
                title="🎓 نمرات",
                semantic_kind="grades",
                context="نمرات منتشرشده",
                list_items=(
                    "ترمیمی ۱ — ۱۷٫۵ از ۲۰",
                    "رادیولوژی ۱ — ۱۸ از ۲۰",
                    "پریودانتیکس ۱ — هنوز نمره‌ای منتشر نشده",
                ),
                actions=(back, home),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "announcements",
            ProviderScreen(
                title="📢 اطلاعیه‌ها",
                semantic_kind="announcements",
                list_items=(
                    "زمان آزمون میان‌ترم ترمیمی ۱ اعلام شد.",
                    "فایل جلسه جدید رادیولوژی ۱ منتشر شد.",
                ),
                pagination="صفحه ۱ از ۳",
                actions=(
                    _cb("صفحه بعد", "v3:announcements:next", role="pagination"),
                    back,
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "resources",
            ProviderScreen(
                title="📚 منابع",
                semantic_kind="resources",
                context="ترمیمی ۱",
                list_items=(
                    "جزوه جلسه ۵ · PDF · دسترسی فعال",
                    "خلاصه جلسه ۴ · PDF · دسترسی فعال",
                    "بانک سؤال فصل ۲ · محافظت‌شده",
                ),
                actions=(
                    _cb("جزوه جلسه ۵", "v3:resource:note5"),
                    _cb("خلاصه جلسه ۴", "v3:resource:sum4"),
                    _cb("بانک سؤال فصل ۲", "v3:resource:qbank2"),
                    back,
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "protected_denied",
            ProviderScreen(
                title="🔒 محتوای محافظت‌شده",
                semantic_kind="protected_denied",
                intro="امکان ارسال این فایل برای حساب شما تأیید نشد. دسترسی یا وضعیت منبع را دوباره بررسی کنید.",
                severity="warning",
                actions=(
                    _cb("بازگشت به منابع", "v3:resources", role="back"),
                    home,
                ),
                edit_policy="new_message",
            ),
        ),
        ProviderExample(
            "protected_ready",
            ProviderScreen(
                title="🔒 دریافت امن",
                semantic_kind="protected_delivery_ready",
                context="ترمیمی ۱ · جزوه جلسه ۵",
                intro="نسخه محافظت‌شده آماده ارسال است.",
                actions=(
                    _cb("بازگشت به منابع", "v3:resources", role="back"),
                    home,
                ),
                protect_content=True,
                edit_policy="new_message",
                plain_text="🔒 دریافت امن\n\nترمیمی ۱ · جزوه جلسه ۵\n\nنسخه محافظت‌شده آماده ارسال است.",
            ),
        ),
        ProviderExample(
            "payment_access",
            ProviderScreen(
                title="💳 خرید و دسترسی",
                semantic_kind="payment_access",
                intro="پرداخت و دسترسی دو وضعیت مستقل هستند.",
                facts=(
                    ProviderFact("وضعیت پرداخت", "پرداخت تأیید شد"),
                    ProviderFact("دسترسی", "در انتظار فعال‌سازی"),
                ),
                actions=(
                    _url("🌐 مشاهده خریدها", f"{web}/orders", role="primary"),
                    back,
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "account",
            ProviderScreen(
                title="👤 حساب",
                semantic_kind="account",
                facts=(
                    ProviderFact("اتصال پیام‌رسان", "فعال"),
                    ProviderFact("فضای آموزشی", "دندان‌پزشکی تهران · ورودی ۱۴۰۲"),
                ),
                actions=(
                    _cb("🏫 تغییر فضای آموزشی", "v3:workspaces"),
                    _url("🌐 باز کردن حساب فانوس", f"{web}/account"),
                    _cb("قطع اتصال پیام‌رسان", "v3:unlink:confirm", role="destructive"),
                    back,
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
        ),
        ProviderExample(
            "error",
            ProviderScreen(
                title="❌ خطا",
                semantic_kind="error",
                intro="اطلاعات این صفحه دریافت نشد.",
                footer="می‌توانید دوباره تلاش کنید.",
                severity="error",
                actions=(
                    _cb("🔄 تلاش دوباره", "v3:retry", role="primary"),
                    home,
                ),
                edit_policy="new_message",
            ),
        ),
        ProviderExample(
            "owner_management",
            ProviderScreen(
                title="⚙️ مدیریت",
                semantic_kind="owner_management",
                context="تلگرام خصوصی",
                sections=(
                    ProviderSection(
                        "وضعیت",
                        "سرویس سالم است.\nنسخه جدید برای بررسی موجود است.",
                    ),
                ),
                actions=(
                    _cb(
                        "🔄 به‌روزرسانی سرور",
                        "v3:deployment:update",
                        role="primary",
                        semantic_id="deployment.update",
                        requires_permission="deployment.manage",
                    ),
                    _cb(
                        "آخرین وضعیت",
                        "v3:deployment:status",
                        semantic_id="deployment.status",
                        requires_permission="deployment.manage",
                    ),
                    back,
                    home,
                ),
                edit_policy="edit_if_safe",
            ),
            telegram_context=ProviderContext(
                private_chat=True,
                canonical_permissions=frozenset({"deployment.manage"}),
            ),
        ),
    )


def rendered_examples(web_origin: str = "https://fanoos.invalid") -> dict[str, dict[str, object]]:
    telegram = TelegramV3Renderer()
    bale = BaleV3Renderer()
    output: dict[str, dict[str, object]] = {}
    for example in representative_examples(web_origin):
        output[example.key] = {
            "telegram": telegram.render(example.screen, context=example.telegram_context),
            "bale": bale.render(example.screen, context=example.bale_context),
        }
    return output
