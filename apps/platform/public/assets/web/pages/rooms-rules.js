/*
 * اتاق مطالعه گروهی: invite links and the wording of a member's day, kept
 * apart from the DOM so it can be tested (tests/web/rooms_rules_test.mjs).
 */
import { faDigits } from './question-stats.js';

/** The invite link for a room's code, on this site. */
export function inviteLink(origin, code) {
    return `${origin}/app/rooms?join=${encodeURIComponent(code)}`;
}

/** The code from whatever was pasted: a whole invite link, or the code alone. */
export function codeFrom(pasted) {
    const text = String(pasted ?? '').trim();
    const match = text.match(/[?&]join=([a-z0-9]+)/i);
    return (match ? match[1] : text).toLowerCase().replace(/[^a-z0-9]/g, '');
}

/** «۱ ساعت و ۲۰ دقیقه» / «۴۵ دقیقه» / «هنوز نخوانده». */
export function minutesText(minutes) {
    if (!minutes) return 'هنوز نخوانده';
    const hours = Math.floor(minutes / 60);
    const rest = minutes % 60;
    if (hours === 0) return `${faDigits(rest)} دقیقه`;
    return rest === 0 ? `${faDigits(hours)} ساعت` : `${faDigits(hours)} ساعت و ${faDigits(rest)} دقیقه`;
}

/** «۴۰ سؤال، ۳۱ درست» or nothing when none were answered. */
export function answersText(member) {
    if (!member.answered) return '';
    return `${faDigits(member.answered)} سؤال، ${faDigits(member.correct)} درست`;
}
