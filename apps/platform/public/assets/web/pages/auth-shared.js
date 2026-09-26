/*
 * Small pieces the sign-in and sign-up forms share. Kept free of the DOM
 * where it can be, so the rules can be tested without a browser.
 */

/** Wires every [data-reveal] button in `root` to show/hide the password it names. */
export function wireRevealToggles(root) {
    for (const button of root.querySelectorAll('[data-reveal]')) {
        const input = root.querySelector(`#${button.dataset.reveal}`);
        if (!input) continue;
        button.addEventListener('click', () => {
            const reveal = input.type === 'password';
            input.type = reveal ? 'text' : 'password';
            button.setAttribute('aria-pressed', String(reveal));
            button.setAttribute('aria-label', reveal ? 'پنهان کردن رمز' : 'نمایش رمز');
            input.focus();
        });
    }
}

/**
 * A rough 0-4 strength for a password, for the meter only: the server's rule
 * is just 8-128 characters, and this never blocks anything. Length counts
 * most; mixing kinds of character counts a little.
 */
export function passwordStrength(password) {
    const value = String(password || '');
    if (value.length === 0) return 0;
    if (value.length < 8) return 1;
    let score = 2;
    const kinds = [/[a-z]/, /[A-Z]/, /[0-9]/, /[^A-Za-z0-9]/].filter((re) => re.test(value)).length;
    if (value.length >= 12 || kinds >= 3) score = 3;
    if (value.length >= 14 && kinds >= 3) score = 4;
    return score;
}

/** Mirrors the server's username rule, after the same lowercasing. */
export function usernameProblem(raw) {
    const value = String(raw || '').trim().toLowerCase();
    if (value === '') return 'یک نام کاربری انتخاب کن.';
    if (/[^\x00-\x7F]/.test(value)) return 'نام کاربری باید با حروف انگلیسی باشد.';
    if (!/^[a-z]/.test(value)) return 'نام کاربری باید با یک حرف انگلیسی شروع شود.';
    if (!/^[a-z][a-z0-9_.]{2,31}$/.test(value)) return 'بین ۳ تا ۳۲ نویسه: حرف انگلیسی، عدد، نقطه یا زیرخط.';
    return '';
}

/** Persian digits for display. */
export function faDigits(value) {
    return String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);
}

/**
 * The current year in the Persian calendar, from the browser's own calendar
 * support. Falls back to an estimate that is right for most of the year.
 */
export function currentJalaliYear(now = new Date()) {
    try {
        const parts = new Intl.DateTimeFormat('en-US-u-ca-persian', { year: 'numeric' }).formatToParts(now);
        const year = Number((parts.find((p) => p.type === 'year') || {}).value);
        if (Number.isInteger(year) && year > 1300) return year;
    } catch {
        // Fall through to the estimate.
    }
    return now.getFullYear() - (now.getMonth() >= 2 ? 621 : 622);
}
