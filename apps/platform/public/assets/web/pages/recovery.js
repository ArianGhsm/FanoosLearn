/*
 * Redeems an owner's recovery link and lets them set a new password.
 *
 * The token lives only in the URL and in the one redeem request; it is
 * never put in a form field, stored, or logged from here. Redeeming
 * establishes a normal session the same way signing in does (the server
 * sets the session cookie on the response) -- this script only has to
 * remember the CSRF token the redeem response returns, since the page was
 * first rendered signed out and carries none of its own.
 */
import { api, ApiError, describeError } from '../foundation/api.js';

const checking = document.getElementById('recovery-checking');
const failed = document.getElementById('recovery-failed');
const failedText = document.getElementById('recovery-failed-text');
const formWrap = document.getElementById('recovery-form-wrap');
const form = document.getElementById('recovery-form');
const submit = document.getElementById('recovery-submit');
const formError = document.getElementById('recovery-form-error');
const formErrorText = document.getElementById('recovery-form-error-text');

function showFailed(message) {
    checking.hidden = true;
    failed.hidden = false;
    failedText.textContent = message;
    failed.setAttribute('tabindex', '-1');
    failed.focus();
}

function showForm() {
    checking.hidden = true;
    formWrap.hidden = false;
}

function showFormError(message) {
    formErrorText.textContent = message;
    formError.hidden = false;
    formError.setAttribute('tabindex', '-1');
    formError.focus();
}

function clearFormError() {
    formError.hidden = true;
    formErrorText.textContent = '';
}

/** The redeem response carries a fresh CSRF token; this page had none at load. */
function rememberCsrf(token) {
    let meta = document.querySelector('meta[name="fanoos-csrf"]');
    if (!meta) {
        meta = document.createElement('meta');
        meta.setAttribute('name', 'fanoos-csrf');
        document.head.appendChild(meta);
    }
    meta.setAttribute('content', token);
}

function redeemErrorMessage(error) {
    if (error instanceof ApiError) {
        if (error.code === 'owner_recovery_token_used') return 'این پیوند قبلاً استفاده شده است.';
        if (error.code === 'owner_recovery_token_expired') return 'این پیوند منقضی شده است.';
        if (error.code === 'owner_recovery_token_invalid') return 'این پیوند نامعتبر است.';
    }
    return describeError(error);
}

async function redeem() {
    const token = new URLSearchParams(window.location.search).get('token') || '';
    if (token === '') {
        showFailed('این پیوند شامل کد لازم نیست.');
        return;
    }
    try {
        const session = await api.post('/auth/recovery/redeem', { token });
        rememberCsrf(session.csrf_token);
        showForm();
    } catch (error) {
        showFailed(redeemErrorMessage(error));
    }
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearFormError();

    const password = form.elements.new_password.value;
    const confirm = form.elements.new_password_confirm.value;

    if (password.length < 8) {
        showFormError('گذرواژه باید دست‌کم ۸ نویسه باشد.');
        return;
    }
    if (password !== confirm) {
        showFormError('تکرار گذرواژه با گذرواژه یکسان نیست.');
        return;
    }

    submit.disabled = true;
    submit.textContent = 'در حال ثبت…';

    try {
        await api.post('/auth/password', { new_password: password });
        window.location.assign('/app');
    } catch (error) {
        submit.disabled = false;
        submit.textContent = 'ثبت گذرواژه و ورود';
        showFormError(describeError(error));
    }
});

form.addEventListener('input', clearFormError);

redeem();
