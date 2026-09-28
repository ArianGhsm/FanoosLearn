/*
 * آمار هر سؤال -- turning the numbers the API sends with a question into the
 * words under it. Pure: no DOM, so it is tested directly.
 *
 * The API withholds other people's share until enough of them have answered
 * (peer_* null); that and "never answered" both read "—", never a zero --
 * "answered correctly 0 times" is a claim, and without data we would be
 * making it up.
 */

export const faDigits = (value) => String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);

/** "امروز", "دیروز", "۳ روز پیش", "۲ هفته پیش", "۴ ماه پیش". */
export function relativeDay(iso, now = Date.now()) {
    const then = Date.parse(iso);
    if (!Number.isFinite(then)) return null;
    const days = Math.max(0, Math.floor((now - then) / 86400000));
    if (days === 0) return 'امروز';
    if (days === 1) return 'دیروز';
    if (days < 14) return `${faDigits(days)} روز پیش`;
    if (days < 30) return `${faDigits(Math.floor(days / 7))} هفته پیش`;
    return `${faDigits(Math.floor(days / 30))} ماه پیش`;
}

/**
 * @returns {{peer: ?string, attempts: ?string, correct: ?string, last: ?string}}
 */
export function statsLines(stats, now = Date.now()) {
    if (!stats || typeof stats !== 'object') {
        return { peer: null, attempts: null, correct: null, last: null };
    }
    const answered = Number(stats.answered) || 0;
    const peer = Number.isInteger(stats.peer_correct_percent) && Number.isInteger(stats.peer_answered)
        ? `٪${faDigits(stats.peer_correct_percent)} از ${faDigits(stats.peer_answered)} پاسخ`
        : null;
    if (answered === 0) {
        return { peer, attempts: 'هنوز نه', correct: null, last: null };
    }
    const when = relativeDay(stats.last_answered_at, now);
    return {
        peer,
        attempts: `${faDigits(answered)} بار`,
        correct: `${faDigits(Number(stats.correct) || 0)} از ${faDigits(answered)}`,
        last: when === null ? null : `${when} · ${stats.last_correct ? 'درست' : 'نادرست'}`,
    };
}

/** The share of everyone who picked each choice, or null when too few answered. */
export function choiceShareLabel(shares, index) {
    if (!shares || !Array.isArray(shares.percent) || !Number.isInteger(shares.percent[index])) return null;
    return `٪${faDigits(shares.percent[index])}`;
}
