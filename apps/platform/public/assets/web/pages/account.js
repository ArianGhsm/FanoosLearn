/*
 * The account page: change password, sign out.
 *
 * Both are POSTs, so both carry the CSRF token the page embeds. Signing out
 * navigates rather than re-rendering: the cookie is gone, and every
 * subsequent page must be fetched as a signed-out visitor.
 *
 * A refused logout used to be swallowed entirely (bare try/catch, no
 * branch), so the page navigated away still signed in and the owner saw
 * "logout does nothing" instead of an error -- the visible symptom of the
 * CSRF token being empty on every rendered page. The session being already
 * gone (401: nothing left to revoke) still just navigates away; anything
 * else the person could act on is shown, not hidden.
 */
import { api, ApiError, describeError } from '../foundation/api.js';

const form = document.getElementById('password-form');
const submit = document.getElementById('password-submit');
const errorBox = document.getElementById('password-error');
const errorText = document.getElementById('password-error-text');
const doneBox = document.getElementById('password-done');
const signout = document.getElementById('signout');
const signoutError = document.getElementById('signout-error');
const signoutErrorText = document.getElementById('signout-error-text');

function fail(message) {
    doneBox.hidden = true;
    errorText.textContent = message;
    errorBox.hidden = false;
    errorBox.setAttribute('tabindex', '-1');
    errorBox.focus();
}

form?.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.hidden = true;
    doneBox.hidden = true;

    const next = form.elements.new_password.value;
    const repeat = form.elements.repeat_password.value;

    // Checked here only to save a round trip and to say something specific;
    // the server enforces its own rules and is the one that decides.
    if (next !== repeat) {
        fail('دو گذرواژه یکی نیستند.');
        return;
    }
    // The server's rule, and the one sign-up uses: a stricter rule here than
    // at sign-up would refuse a student the very password they signed up with.
    if (next.length < 8) {
        fail('گذرواژه باید دست‌کم ۸ نویسه باشد.');
        return;
    }

    submit.disabled = true;
    submit.textContent = 'در حال ثبت…';
    try {
        await api.post('/auth/password', { new_password: next });
        form.reset();
        doneBox.hidden = false;
    } catch (error) {
        fail(error instanceof ApiError && error.status === 422
            ? 'این گذرواژه پذیرفته نشد. گذرواژه‌ی طولانی‌تر و کمتر قابل‌حدس انتخاب کن.'
            : describeError(error));
    } finally {
        submit.disabled = false;
        submit.textContent = 'ثبت گذرواژه';
    }
});

signout?.addEventListener('click', async () => {
    signoutError.hidden = true;
    signout.disabled = true;
    signout.textContent = 'در حال خروج…';
    try {
        await api.post('/auth/logout', {});
    } catch (error) {
        // Session already gone server-side: nothing left to revoke, so the
        // right destination is still the signed-out front door.
        if (!(error instanceof ApiError && error.isSessionExpired)) {
            signout.disabled = false;
            signout.textContent = 'خروج از حساب';
            signoutErrorText.textContent = describeError(error);
            signoutError.hidden = false;
            signoutError.setAttribute('tabindex', '-1');
            signoutError.focus();
            return;
        }
    }
    window.location.assign('/');
});

/*
 * The account's phone number: optional at sign-up, added here. A code goes to
 * the number by SMS and the number is attached once the code is entered.
 */
const phoneValue = document.getElementById('phone-value');
const phoneForm = document.getElementById('phone-form');
const codeForm = document.getElementById('phone-code-form');
const phoneError = document.getElementById('phone-error');
const phoneErrorText = document.getElementById('phone-error-text');
const phoneDone = document.getElementById('phone-done');
let challengeToken = '';

const PHONE_ERRORS = {
    onboarding_phone_invalid: 'شماره‌ی موبایل درست نیست؛ به شکل ۰۹۱۲۳۴۵۶۷۸۹ بنویس.',
    account_phone_in_use: 'این شماره به حساب دیگری وصل است.',
    onboarding_otp_send_failed: 'پیامک فرستاده نشد. کمی بعد دوباره امتحان کن.',
    onboarding_otp_cooldown: 'کد تازه همین الان فرستاده شد؛ یک دقیقه صبر کن.',
    onboarding_otp_rate_limited: 'چند بار پشت سر هم کد خواستی؛ کمی بعد دوباره امتحان کن.',
    onboarding_challenge_not_found: 'درخواست تأیید پیدا نشد؛ دوباره کد بگیر.',
    onboarding_challenge_used: 'این کد قبلاً استفاده شده؛ دوباره کد بگیر.',
    onboarding_otp_code_invalid: 'کد درست نیست.',
    onboarding_otp_code_expired: 'کد منقضی شده؛ دوباره کد بگیر.',
    onboarding_otp_attempts_exceeded: 'چند بار کد اشتباه وارد شد؛ دوباره کد بگیر.',
    onboarding_challenge_expired: 'زمان تأیید تمام شد؛ دوباره کد بگیر.',
};

function phoneFail(error) {
    phoneDone.hidden = true;
    phoneErrorText.textContent = (error instanceof ApiError && PHONE_ERRORS[error.code]) || describeError(error);
    phoneError.hidden = false;
}

async function loadPhone() {
    if (!phoneValue) return;
    try {
        const current = await api.get('/account/phone');
        phoneValue.textContent = current?.phone_masked
            ? current.phone_masked.replace(/^\+98/, '0')
            : 'ثبت نشده';
    } catch {
        phoneValue.textContent = '—';
    }
}

phoneForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    phoneError.hidden = true;
    const button = phoneForm.querySelector('button');
    button.disabled = true;
    try {
        const result = await api.post('/account/phone/code', { phone: phoneForm.elements.phone.value.trim() });
        challengeToken = result.challenge_token;
        codeForm.hidden = false;
        codeForm.elements.code.value = '';
        codeForm.elements.code.focus();
    } catch (error) {
        phoneFail(error);
    } finally {
        button.disabled = false;
    }
});

codeForm?.addEventListener('submit', async (event) => {
    event.preventDefault();
    phoneError.hidden = true;
    const button = codeForm.querySelector('button');
    button.disabled = true;
    try {
        await api.post('/account/phone/verify', { challenge_token: challengeToken, code: codeForm.elements.code.value.trim() });
        codeForm.hidden = true;
        phoneForm.reset();
        phoneDone.hidden = false;
        await loadPhone();
    } catch (error) {
        phoneFail(error);
    } finally {
        button.disabled = false;
    }
});

loadPhone();

/*
 * Connecting the bots to this account. "Connect" asks for a one-time link
 * code and opens the bot with it (a start link); the bot takes the code and
 * ties that chat to this account. The code is also shown as a /link command,
 * for when the start link does not open the app.
 */
const botsBox = document.getElementById('bots');
const botsList = document.getElementById('bots-list');
const botsError = document.getElementById('bots-error');
const botsErrorText = document.getElementById('bots-error-text');
const BOT_NAMES = { bale: 'بله', telegram: 'تلگرام' };
const BOT_LINKS = {
    bale: (bot, token) => `https://ble.ir/${bot}?start=${encodeURIComponent(token)}`,
    telegram: (bot, token) => `https://t.me/${bot}?start=${encodeURIComponent(token)}`,
};

function botsFail(error) {
    botsErrorText.textContent = describeError(error);
    botsError.hidden = false;
}

function node(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
}

function botRow(link) {
    const bot = botsBox.dataset[`${link.platform}Bot`] || '';
    const row = node('div', `a-bot${link.linked ? ' is-linked' : ''}`);
    const label = node('div', 'a-bot__label');
    label.append(node('strong', '', `ربات ${BOT_NAMES[link.platform] ?? link.platform}`),
        node('span', 'f-muted', link.linked ? '✓ وصل است' : (bot ? `@${bot}` : 'در دسترس نیست')));
    row.append(label);

    const action = node('button', `f-btn ${link.linked ? 'f-btn--ghost' : 'f-btn--primary'}`, link.linked ? 'قطع اتصال' : 'اتصال');
    action.type = 'button';
    action.disabled = !link.linked && !bot;
    action.addEventListener('click', async () => {
        botsError.hidden = true;
        action.disabled = true;
        try {
            if (link.linked) {
                if (!window.confirm(`اتصال ربات ${BOT_NAMES[link.platform]} به این حساب قطع شود؟`)) {
                    action.disabled = false;
                    return;
                }
                await api.post(`/messaging/links/${link.platform}/revoke`, { reason: 'user_unlink' });
                await loadBots();
                return;
            }
            const challenge = await api.post('/messaging/link-challenges', { platform: link.platform });
            const url = BOT_LINKS[link.platform](bot, challenge.challenge_token);
            window.open(url, '_blank', 'noopener');
            const help = node('div', 'a-bot__help');
            const open = node('a', 'f-btn f-btn--ghost', 'باز کردن ربات');
            open.href = url;
            open.target = '_blank';
            open.rel = 'noopener';
            const command = node('code', 'a-bot__code', `/link ${challenge.challenge_token}`);
            command.dir = 'ltr';
            help.append(
                node('p', 'f-tiny', 'ربات باز شد؟ دکمه‌ی «شروع» را بزن. اگر باز نشد، این را در ربات بفرست (تا چند دقیقه معتبر است):'),
                command, open,
                node('p', 'f-tiny', 'بعد از وصل شدن، همین صفحه را تازه کن.'),
            );
            row.querySelector('.a-bot__help')?.remove();
            row.append(help);
            action.disabled = false;
        } catch (error) {
            botsFail(error);
            action.disabled = false;
        }
    });
    row.insertBefore(action, row.children[1] ?? null);
    return row;
}

async function loadBots() {
    if (!botsList) return;
    try {
        const result = await api.get('/messaging/links');
        botsList.replaceChildren(...(result?.links ?? []).map(botRow));
    } catch (error) {
        botsList.replaceChildren(node('p', 'f-muted', 'وضعیت اتصال خوانده نشد.'));
        botsFail(error);
    } finally {
        botsList.setAttribute('aria-busy', 'false');
    }
}

loadBots();
