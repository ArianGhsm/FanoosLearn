/*
 * امتیاز روزانه: the wording and arithmetic of the points page, kept apart
 * from the DOM so it can be tested (tests/web/points_rules_test.mjs).
 */
import { faDigits } from './question-stats.js';

/** Share of the daily goal reached, 0-100, never above. */
export function goalPercent(points, goal) {
    if (!goal || goal <= 0) return 0;
    return Math.max(0, Math.min(100, Math.round((points * 100) / goal)));
}

/** The line under today's ring. */
export function goalLine(points, goal) {
    if (points >= goal) return `به هدف امروز رسیدی — ${faDigits(points)} امتیاز`;
    if (points === 0) return `هنوز امتیازی نگرفته‌ای؛ هدف امروز ${faDigits(goal)} امتیاز است`;
    return `${faDigits(goal - points)} امتیاز تا هدف امروز`;
}

/** "رتبه‌ی ۳ از ۴۵ نفر" -- or how many are active, when the student has no place yet. */
export function rankLine(standing) {
    const active = standing?.active ?? 0;
    if (standing?.rank) return `رتبه‌ی ${faDigits(standing.rank)} از ${faDigits(active)} نفر`;
    if (active === 0) return 'هنوز کسی امتیاز نگرفته';
    return `رتبه‌ای نداری · ${faDigits(active)} نفر امتیاز گرفته‌اند`;
}

/** Bar heights as percentages of the largest value; all zero when there is nothing. */
export function barHeights(values) {
    const max = Math.max(0, ...values);
    return values.map((value) => (max === 0 ? 0 : Math.round((value * 100) / max)));
}

/** The rules list, from the server's numbers. */
export function ruleLines(data) {
    const p = data.points_per_answer || {};
    return [
        `پاسخ درست به سؤال آسان ${faDigits(p.easy ?? 5)}، متوسط ${faDigits(p.medium ?? 10)} و دشوار ${faDigits(p.hard ?? 20)} امتیاز دارد. سختی سؤال از روی پاسخ همه‌ی کاربران سنجیده می‌شود.`,
        'هر سؤال در یک روز فقط یک بار امتیاز می‌دهد؛ دوباره زدنش در همان روز امتیازی اضافه نمی‌کند.',
        'پاسخ سفید و پاسخی که پیش از انتخاب دیده شده امتیاز ندارد.',
        `هر روز که به ${faDigits(data.goal)} امتیاز برسی، یک سکه می‌گیری.`,
        'رتبه‌ها فقط میان کاربران همین فضای آموزشی است؛ امتیاز و نام دیگران به کسی نشان داده نمی‌شود.',
        'هفته از شنبه شروع می‌شود و ماه، ماه شمسی است.',
    ];
}
