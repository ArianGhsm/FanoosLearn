/*
 * Dates and items of the calendar and study-plan pages
 * (tests/web/plan_rules_test.mjs). Dates are shown in the Persian calendar.
 */

const dayFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { weekday: 'long', day: 'numeric', month: 'long' });
const shortFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long', hour: '2-digit', minute: '2-digit', timeZone: 'Asia/Tehran' });
const fullFormat = new Intl.DateTimeFormat('fa-IR-u-ca-persian', { day: 'numeric', month: 'long', year: 'numeric' });

/** "شنبه ۱۲ مهر" for a plan day ('Y-m-d', a calendar date, read at noon UTC so no zone moves it). */
export function planDay(date) {
    return dayFormat.format(new Date(`${date}T12:00:00Z`));
}

/** "۱۲ مهر ۱۴۰۵" for a 'Y-m-d' date. */
export function fullDay(date) {
    return fullFormat.format(new Date(`${date}T12:00:00Z`));
}

/** An exam window in Tehran time: "از ۱۲ مهر، ۱۸:۰۰ تا ۱۹ مهر، ۲۳:۰۰". */
export function windowLabel(opensAt, closesAt) {
    return `از ${shortFormat.format(new Date(opensAt))} تا ${shortFormat.format(new Date(closesAt))}`;
}

/** A datetime-local input value (the browser's local time) as an ISO instant; '' when empty or invalid. */
export function localInputToIso(value) {
    if (!value) return '';
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? '' : date.toISOString();
}

/** What a plan item asks for, in words. */
export function itemLabel(item) {
    switch (item?.kind) {
        case 'topic': return `${item.subject}: ${item.topic}`;
        case 'subject': return `${item.subject}: سؤال‌های مبحث‌بندی‌نشده`;
        case 'reading': return `مطالعه‌ی ${item.subject} از منابع`;
        case 'review': return 'مرور هوشمند: سؤال‌هایی که غلط زده‌ای';
        case 'mock': return 'آزمون جامع: یک دوره‌ی کامل از آزمون‌های گذشته، زمان‌دار';
        case 'rest': return 'روز آخر: مرور سبک و استراحت';
        default: return '';
    }
}

/** Groups plan days into weeks of seven, in order. */
export function weeks(days) {
    const out = [];
    for (let i = 0; i < days.length; i += 7) out.push(days.slice(i, i + 7));
    return out;
}
