/*
 * Upgrades the sign-in form to an in-place submit.
 *
 * The form works as a plain POST if this never loads; this only replaces the
 * full-page round trip with an inline error so a wrong password does not
 * lose what was typed.
 */
import { api, ApiError, describeError } from '../foundation/api.js';

const form = document.getElementById('login-form');
const submit = document.getElementById('login-submit');
const errorBox = document.getElementById('login-error');
const errorText = document.getElementById('login-error-text');

function showError(message) {
    errorText.textContent = message;
    errorBox.hidden = false;
    // Move focus to the message: a sighted user sees it appear, but a screen
    // reader user would otherwise be left at the submit button with no idea
    // anything changed.
    errorBox.setAttribute('tabindex', '-1');
    errorBox.focus();
}

function clearError() {
    errorBox.hidden = true;
    errorText.textContent = '';
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearError();

    const identifier = form.elements.identifier.value.trim();
    const password = form.elements.password.value;

    if (identifier === '' || password === '') {
        showError('شناسه و گذرواژه را وارد کن.');
        return;
    }

    submit.disabled = true;
    submit.textContent = 'در حال ورود…';

    try {
        await api.post('/auth/login', { identifier, password });
        // The session cookie is set by the response; a full navigation from
        // here means the next page is rendered by the server already signed
        // in, with no flash of a signed-out shell.
        window.location.assign('/app');
    } catch (error) {
        submit.disabled = false;
        submit.textContent = 'ورود';
        if (error instanceof ApiError && error.status === 401) {
            // Never say which half was wrong: that tells an attacker whether
            // an identifier exists.
            showError('شناسه یا گذرواژه درست نیست.');
            return;
        }
        showError(describeError(error));
    }
});

form.addEventListener('input', clearError);
