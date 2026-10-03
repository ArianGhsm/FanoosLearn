/*
 * The timer's arithmetic (tests/web/timer_rules_test.mjs). Time is always
 * measured from a wall-clock end instant, never by counting ticks, so a tab
 * the browser put to sleep still shows the right time when it wakes.
 */

/** "۰۵:۰۹" from seconds; never negative. */
export function clock(seconds, faDigits) {
    const s = Math.max(0, Math.ceil(seconds));
    const mm = String(Math.floor(s / 60)).padStart(2, '0');
    const ss = String(s % 60).padStart(2, '0');
    return faDigits(`${mm}:${ss}`);
}

/** Seconds left until `endsAt` (ms) at `now` (ms). */
export function remaining(endsAt, now) {
    return Math.max(0, (endsAt - now) / 1000);
}

/** Whole minutes of a focus block worth recording when it is stopped early; 0 below one minute. */
export function elapsedMinutes(startedAt, now, focusMinutes) {
    const minutes = Math.floor((now - startedAt) / 60000);
    return Math.max(0, Math.min(focusMinutes, minutes));
}

/** After focus comes a break, after a break comes focus. */
export function nextPhase(phase) {
    return phase === 'focus' ? 'break' : 'focus';
}
