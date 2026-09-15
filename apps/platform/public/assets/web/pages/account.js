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
    if (next.length < 12) {
        fail('گذرواژه باید دست‌کم ۱۲ نویسه باشد.');
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
