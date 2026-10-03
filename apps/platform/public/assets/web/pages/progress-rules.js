/*
 * داشبورد پیشرفت -- the arithmetic behind the drawings, kept apart from the
 * DOM so it is tested directly.
 */

/**
 * 0..4 for a day's heat square. 0 is "nothing"; the rest split the busiest
 * day into quarters, so one huge day does not flatten every other day to
 * the lightest shade -- a square is never blank when something was done.
 */
export function heatLevel(count, max) {
    if (!(count > 0) || !(max > 0)) return 0;
    return Math.min(4, Math.max(1, Math.ceil((count / max) * 4)));
}

/**
 * The days as columns of weeks, Saturday first (the Iranian week), for a grid
 * drawn column by column. Leading cells before the first day are null.
 *
 * @param {{date: string}[]} days oldest first, consecutive
 * @returns {Array<Array<object|null>>}
 */
export function weekColumns(days) {
    if (days.length === 0) return [];
    // getUTCDay: 0 Sunday .. 6 Saturday; Saturday-first index is (d + 1) % 7.
    const first = new Date(`${days[0].date}T00:00:00Z`).getUTCDay();
    const cells = [...Array((first + 1) % 7).fill(null), ...days];
    const columns = [];
    for (let i = 0; i < cells.length; i += 7) {
        const column = cells.slice(i, i + 7);
        while (column.length < 7) column.push(null);
        columns.push(column);
    }
    return columns;
}

/**
 * Points for a score line in a width x height box, 0% at the bottom and
 * 100% at the top, spread evenly left to right in the order given. The page
 * is right-to-left, so the caller mirrors the box -- oldest on the right.
 *
 * @param {number[]} scores percentages
 * @returns {{x: number, y: number}[]}
 */
export function trendPoints(scores, width, height, pad = 8) {
    if (scores.length === 0) return [];
    const span = width - pad * 2;
    const step = scores.length === 1 ? 0 : span / (scores.length - 1);
    return scores.map((score, i) => ({
        x: scores.length === 1 ? width / 2 : pad + i * step,
        y: pad + (1 - Math.min(100, Math.max(0, score)) / 100) * (height - pad * 2),
    }));
}

/** Average of the last `n` scores, or null. */
export function recentAverage(scores, n = 5) {
    const tail = scores.slice(-n);
    if (tail.length === 0) return null;
    return Math.round(tail.reduce((a, b) => a + b, 0) / tail.length);
}

/** Up, down or flat: the last `n` against the `n` before them, with a 3-point dead band. */
export function direction(scores, n = 5) {
    if (scores.length < n * 2) return null;
    const now = recentAverage(scores, n);
    const before = recentAverage(scores.slice(0, -n), n);
    if (now - before >= 3) return 'up';
    if (before - now >= 3) return 'down';
    return 'flat';
}

/**
 * Study time in words: "۴۵ دقیقه", "۲ ساعت", "۲ ساعت و ۱۰ دقیقه"; "—" for none.
 */
export function studyTime(minutes, faDigits) {
    const total = Math.max(0, Math.round(Number(minutes) || 0));
    if (total === 0) return '—';
    const hours = Math.floor(total / 60);
    const rest = total % 60;
    if (hours === 0) return `${faDigits(rest)} دقیقه`;
    return rest === 0 ? `${faDigits(hours)} ساعت` : `${faDigits(hours)} ساعت و ${faDigits(rest)} دقیقه`;
}
