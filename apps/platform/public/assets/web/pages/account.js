/*
 * The account page: change password, sign out.
 *
 * Both are POSTs, so both carry the CSRF token the page embeds. Signing out
 * navigates rather than re-rendering: the cookie is gone, and every
 * subsequent page must be fetched as a signed-out visitor.
 */
import { api, ApiError, describeError } from '../foundation/api.js';

const form = document.getElementById('password-form');
const submit = document.getElementById('password-submit');
const errorBox = document.getElementById('password-error');
const errorText = document.getElementById('password-error-text');
const doneBox = document.getElementById('password-done');
const signout = document.getElementById('signout');

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
    signout.disabled = true;
    signout.textContent = 'در حال خروج…';
    try {
        await api.post('/auth/logout', {});
    } catch {
        // The session may already be gone on the server. Either way the
        // right destination is the signed-out front door, so never strand
        // the person on a page they can no longer use.
    }
    window.location.assign('/');
});
