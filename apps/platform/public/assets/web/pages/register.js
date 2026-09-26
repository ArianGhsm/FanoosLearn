/*
 * The sign-up wizard.
 *
 * Shows one step at a time, checks each step before moving on with the same
 * rules the server applies, and on submit sends the whole form once. The
 * server is still the authority: whatever it refuses, the wizard goes back to
 * the step that holds the field and says why, in Persian, next to it.
 */
import { api, ApiError, describeError } from '../foundation/api.js';
import { currentJalaliYear, faDigits, passwordStrength, usernameProblem, wireRevealToggles } from './auth-shared.js';

const form = document.getElementById('register-form');
const steps = [...form.querySelectorAll('.f-wizard__step')];
const track = [...document.querySelectorAll('#register-track li')];
const errorBox = document.getElementById('register-error');
const errorText = document.getElementById('register-error-text');
const submit = document.getElementById('register-submit');
const disciplineBox = document.getElementById('discipline-options');
const provinceSelect = document.getElementById('province');
const institutionSelect = document.getElementById('institution');
const yearSelect = document.getElementById('entry_year');
const recap = document.getElementById('register-recap');
const strength = document.getElementById('password-strength');

const labels = {
    entry_term: { first: 'نیمسال اول', second: 'نیمسال دوم' },
    course_type: { daily: 'روزانه / تعهدی', tuition: 'شهریه‌پرداز', international: 'بین‌الملل' },
};

/* Which step holds the field each server error is about. */
const ERRORS = {
    username_taken: [1, 'username', 'این نام کاربری را کس دیگری گرفته؛ یکی دیگر امتحان کن.'],
    username_invalid: [1, 'username', 'نام کاربری فقط حروف انگلیسی، عدد، نقطه و زیرخط؛ ۳ تا ۳۲ نویسه.'],
    password_invalid: [1, 'password', 'رمز باید دست‌کم ۸ نویسه باشد.'],
    name_required: [1, 'first_name', 'نام و نام خانوادگی را با حروف بنویس.'],
    discipline_required: [2, null, 'رشته‌ات را انتخاب کن.'],
    discipline_not_found: [2, null, 'این رشته دیگر در فهرست نیست؛ دوباره انتخاب کن.'],
    institution_invalid: [3, 'institution_id', 'دانشگاه انتخاب‌شده معتبر نیست.'],
    institution_not_found: [3, 'institution_id', 'دانشگاه انتخاب‌شده پیدا نشد.'],
    entry_year_invalid: [3, 'entry_year', 'سال ورود معتبر نیست.'],
    entry_term_invalid: [3, null, 'نیمسال ورود معتبر نیست.'],
    course_type_invalid: [3, null, 'نوع دوره معتبر نیست.'],
    student_number_invalid: [3, 'student_number', 'شماره دانشجویی فقط عدد است.'],
    registration_throttled: [0, null, 'از این شبکه در یک ساعت گذشته چند حساب ساخته شده. کمی بعد دوباره امتحان کن.'],
};

const TILE_HUES = ['subject', 'tag', 'source', 'difficulty'];

let current = 1;
let provinceNames = new Map();
let institutionNames = new Map();
let disciplineNames = new Map();

wireRevealToggles(form);
// With the script running, steps are shown one at a time.
form.classList.add('is-wizard');
showStep(1, false);
loadOptions();
loadProvinces();
fillYears();

function showStep(step, focus = true) {
    current = step;
    steps.forEach((node) => {
        const active = Number(node.dataset.step) === step;
        node.hidden = !active;
        if (active) {
            node.removeAttribute('data-active');
            void node.offsetWidth;
            node.setAttribute('data-active', '');
        }
    });
    track.forEach((item, index) => {
        item.classList.toggle('is-current', index + 1 === step);
        item.classList.toggle('is-done', index + 1 < step);
    });
    if (step === 3) renderRecap();
    if (focus) {
        const first = steps[step - 1].querySelector('input:not([type=radio]), select, input[type=radio]');
        first?.focus({ preventScroll: true });
        steps[step - 1].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

function fieldError(name, message) {
    const input = form.elements[name];
    const node = input instanceof RadioNodeList ? null : input;
    if (!node) return;
    node.setAttribute('aria-invalid', 'true');
    const holder = node.closest('.f-field');
    let slot = holder?.querySelector('.f-field__error');
    if (!slot && holder) {
        slot = document.createElement('span');
        slot.className = 'f-field__error';
        slot.id = `${node.id}-error`;
        holder.append(slot);
        node.setAttribute('aria-describedby', `${node.getAttribute('aria-describedby') || ''} ${slot.id}`.trim());
    }
    if (slot) slot.textContent = message;
}

function clearFieldErrors() {
    form.querySelectorAll('[aria-invalid="true"]').forEach((node) => node.removeAttribute('aria-invalid'));
    form.querySelectorAll('.f-field__error').forEach((node) => { node.textContent = ''; });
}

function showError(message) {
    errorText.textContent = message;
    errorBox.hidden = true;
    void errorBox.offsetWidth;
    errorBox.hidden = false;
}

function clearError() {
    errorBox.hidden = true;
}

/* The same rules the server applies; returns true when the step may be left. */
function validateStep(step) {
    clearFieldErrors();
    clearError();
    if (step === 1) {
        let ok = true;
        const nameRule = /^[\p{L}\p{M}‌ .'-]+$/u;
        for (const name of ['first_name', 'last_name']) {
            const value = form.elements[name].value.trim();
            if (value === '' || !nameRule.test(value)) {
                fieldError(name, value === '' ? 'این را پر کن.' : 'فقط حروف.');
                ok = false;
            }
        }
        const usernameMessage = usernameProblem(form.elements.username.value);
        if (usernameMessage) {
            fieldError('username', usernameMessage);
            ok = false;
        }
        if (form.elements.password.value.length < 8) {
            fieldError('password', 'رمز باید دست‌کم ۸ نویسه باشد.');
            ok = false;
        }
        if (!ok) form.querySelector('[aria-invalid="true"]')?.focus();
        return ok;
    }
    if (step === 2) {
        if (!form.querySelector('input[name="discipline_id"]:checked')) {
            showError('رشته‌ات را انتخاب کن.');
            return false;
        }
        return true;
    }
    const number = form.elements.student_number.value.trim().replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)));
    if (number !== '' && !/^[0-9]{4,20}$/.test(number)) {
        fieldError('student_number', 'فقط عدد، بین ۴ تا ۲۰ رقم.');
        return false;
    }
    return true;
}

form.addEventListener('click', (event) => {
    const next = event.target.closest('[data-next]');
    const back = event.target.closest('[data-back]');
    if (next && validateStep(current)) showStep(current + 1);
    if (back) showStep(current - 1);
});

// Enter in a text field moves forward rather than submitting a half-filled form.
form.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter' || event.target.tagName !== 'INPUT' || current === 3) return;
    event.preventDefault();
    if (validateStep(current)) showStep(current + 1);
});

form.addEventListener('input', (event) => {
    if (event.target.name === 'password') {
        strength.dataset.level = String(passwordStrength(event.target.value));
    }
    if (event.target.getAttribute('aria-invalid') === 'true') {
        event.target.removeAttribute('aria-invalid');
        const slot = event.target.closest('.f-field')?.querySelector('.f-field__error');
        if (slot) slot.textContent = '';
    }
});

form.addEventListener('change', (event) => {
    if (event.target.name === 'discipline_id') {
        clearError();
        // A tile is a single tap: choosing one is the whole of this step.
        window.setTimeout(() => { if (current === 2) showStep(3); }, 220);
    }
    if (event.target === provinceSelect) loadInstitutions(provinceSelect.value);
    if (current === 3) renderRecap();
});

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    if (current !== 3) {
        if (validateStep(current)) showStep(current + 1);
        return;
    }
    for (const step of [1, 2, 3]) {
        if (!validateStep(step)) {
            showStep(step);
            return;
        }
    }

    const data = new FormData(form);
    const body = {
        username: String(data.get('username') || '').trim().toLowerCase(),
        password: String(data.get('password') || ''),
        first_name: String(data.get('first_name') || '').trim(),
        last_name: String(data.get('last_name') || '').trim(),
        discipline_id: String(data.get('discipline_id') || ''),
    };
    for (const optional of ['institution_id', 'entry_year', 'entry_term', 'course_type', 'student_number']) {
        const value = String(data.get(optional) || '').trim();
        if (value !== '') body[optional] = value;
    }

    submit.disabled = true;
    submit.textContent = 'در حال ساخت حساب…';
    try {
        await api.post('/auth/register', body);
        window.location.assign('/app?welcome=1');
    } catch (error) {
        submit.disabled = false;
        submit.textContent = 'ساخت حساب';
        const known = error instanceof ApiError ? ERRORS[error.code] : null;
        if (known) {
            const [step, field, message] = known;
            if (step > 0) showStep(step);
            showError(message);
            if (field) {
                fieldError(field, message);
                form.elements[field]?.focus?.();
            }
            return;
        }
        showError(describeError(error));
    }
});

async function loadOptions() {
    try {
        const options = await api.get('/signup/options');
        const disciplines = Array.isArray(options?.disciplines) ? options.disciplines : [];
        if (disciplines.length === 0) {
            disciplineBox.replaceChildren(textNode('p', 'f-muted', 'هنوز هیچ رشته‌ای تعریف نشده.'));
            return;
        }
        disciplineNames = new Map(disciplines.map((d) => [String(d.id), String(d.name)]));
        disciplineBox.replaceChildren(...disciplines.map((discipline, index) => {
            const tile = document.createElement('label');
            tile.className = 'f-tile';
            // Each field its own information hue, in list order, so two
            // fields that share an initial still look like different things.
            const hue = TILE_HUES[index % TILE_HUES.length];
            tile.style.setProperty('--tile-hue', `var(--hue-${hue})`);
            tile.style.setProperty('--tile-soft', `var(--hue-${hue}-soft)`);
            const input = document.createElement('input');
            input.type = 'radio';
            input.name = 'discipline_id';
            input.value = String(discipline.id);
            const name = String(discipline.name || '');
            tile.append(
                input,
                capMark(),
                textNode('span', 'f-tile__name', name),
                textNode('span', 'f-tile__hint', discipline.has_library ? 'بانک آزمون آماده است' : 'به‌زودی'),
            );
            return tile;
        }));
    } catch (error) {
        disciplineBox.replaceChildren(textNode('p', 'f-muted', `فهرست رشته‌ها خوانده نشد: ${describeError(error)}`));
    } finally {
        disciplineBox.setAttribute('aria-busy', 'false');
    }
}

async function loadProvinces() {
    try {
        const result = await api.get('/directory/provinces');
        const items = sortedByName(result?.items);
        provinceNames = new Map(items.map((p) => [String(p.id), String(p.name)]));
        provinceSelect.append(...items.map((p) => option(p.id, p.name)));
    } catch {
        // Optional step: an unreadable list just leaves the field empty.
    }
}

async function loadInstitutions(provinceId) {
    institutionSelect.replaceChildren(option('', provinceId ? 'در حال خواندن…' : 'اول استان را انتخاب کن'));
    institutionSelect.disabled = true;
    institutionNames = new Map();
    if (!provinceId) return;
    try {
        const result = await api.get(`/directory/provinces/${encodeURIComponent(provinceId)}/institutions`);
        const items = sortedByName(result?.items);
        institutionNames = new Map(items.map((i) => [String(i.id), String(i.name)]));
        institutionSelect.replaceChildren(
            option('', items.length ? 'انتخاب کن' : 'دانشگاهی در این استان ثبت نشده'),
            ...items.map((i) => option(i.id, i.name)),
        );
        institutionSelect.disabled = items.length === 0;
    } catch (error) {
        institutionSelect.replaceChildren(option('', 'فهرست خوانده نشد'));
    }
}

function fillYears() {
    const now = currentJalaliYear();
    for (let year = now; year >= now - 12; year--) {
        yearSelect.append(option(String(year), faDigits(year)));
    }
}

function renderRecap() {
    const data = new FormData(form);
    const rows = [
        ['نام', `${data.get('first_name') || ''} ${data.get('last_name') || ''}`.trim()],
        ['نام کاربری', String(data.get('username') || '').trim().toLowerCase()],
        ['رشته', disciplineNames.get(String(data.get('discipline_id') || '')) || ''],
        ['دانشگاه', institutionNames.get(String(data.get('institution_id') || '')) || ''],
        ['ورودی', [data.get('entry_year') ? faDigits(data.get('entry_year')) : '', labels.entry_term[data.get('entry_term')] || '']
            .filter(Boolean).join(' · ')],
        ['نوع دوره', labels.course_type[data.get('course_type')] || ''],
    ].filter(([, value]) => value !== '');
    recap.replaceChildren(...rows.flatMap(([term, value]) => [textNode('dt', '', term), textNode('dd', '', value)]));
}

/* A mortarboard: the field-of-study mark, drawn rather than lettered. */
function capMark() {
    const mark = document.createElement('span');
    mark.className = 'f-tile__mark';
    mark.setAttribute('aria-hidden', 'true');
    const ns = 'http://www.w3.org/2000/svg';
    const svg = document.createElementNS(ns, 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    for (const d of ['M2 9l10-5 10 5-10 5z', 'M6 11v5c0 1.5 2.7 3 6 3s6-1.5 6-3v-5', 'M22 9v6']) {
        const path = document.createElementNS(ns, 'path');
        path.setAttribute('d', d);
        svg.append(path);
    }
    mark.append(svg);
    return mark;
}

function sortedByName(items) {
    const collator = new Intl.Collator('fa');
    return (Array.isArray(items) ? items : []).slice().sort((a, b) => collator.compare(String(a.name), String(b.name)));
}

function option(value, label) {
    const node = document.createElement('option');
    node.value = String(value);
    node.textContent = String(label);
    return node;
}

function textNode(tag, className, value) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = value;
    return node;
}
