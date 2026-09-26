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
