/*
 * «آزمون من»: the exam a student is preparing for, chosen on a chip that
 * opens a small panel -- every exam, or one exam type and, for a specialty
 * exam, one specialty. It narrows what the bank, the exams and the
 * references show first, and is changed in one tap from any of them.
 *
 * Kept in this browser (localStorage, like the theme), so it never blocks a
 * page: unreadable storage simply means "every exam". Pages listen for the
 * `fanoos:goal` event to redraw when it changes.
 */
import { ALL_EXAMS, goalLabel, normalizeGoal } from './bank-rules.js';

const KEY = 'fanoos.goal';

export function readGoal(typeKeys) {
    let stored = null;
    try {
        stored = JSON.parse(window.localStorage.getItem(KEY) || 'null');
    } catch {
        stored = null;
    }
    return normalizeGoal(stored, typeKeys);
}

export function saveGoal(goal) {
    try {
        window.localStorage.setItem(KEY, JSON.stringify(goal));
    } catch {
        // Storage refused (private window): the choice lasts for this page only.
    }
    window.dispatchEvent(new CustomEvent('fanoos:goal', { detail: goal }));
}

function node(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined) element.textContent = text;
    return element;
}

/**
 * The chip and its panel. `types`: [{key, name}]; `specialties`: for each
 * exam type whose papers are one specialty each, its [{key, name}] -- the
 * data says which types those are. Calls onChange(goal) after saving it.
 */
export function goalPicker({ types, specialties = {}, goal, onChange }) {
    const wrap = node('div', 'g-goal');
    const chip = node('button', 'g-goal__chip');
    chip.type = 'button';
    chip.setAttribute('aria-expanded', 'false');
    chip.setAttribute('aria-haspopup', 'true');
    const panel = node('div', 'g-goal__panel');
    panel.hidden = true;
    panel.setAttribute('role', 'group');
    panel.setAttribute('aria-label', 'آزمون من');
    let current = { ...goal };

    const specialtiesOf = (type) => specialties[type] ?? [];
    const specialtyName = (key) => specialtiesOf(current.type).find((s) => s.key === key)?.name;
    const label = () => {
        chip.replaceChildren(node('span', 'g-goal__hint', 'آزمون من:'), node('strong', '', goalLabel(current, types, specialtyName(current.specialty))));
        const caret = node('span', 'g-goal__caret');
        caret.setAttribute('aria-hidden', 'true');
        chip.append(caret);
    };

    const option = (text, pressed, onPick) => {
        const button = node('button', 'g-goal__option', text);
        button.type = 'button';
        button.setAttribute('aria-pressed', String(pressed));
        button.addEventListener('click', onPick);
        return button;
    };

    const choose = (next) => {
        current = next;
        saveGoal(current);
        label();
        draw();
        onChange?.(current);
    };

    const draw = () => {
        panel.replaceChildren();
        panel.append(node('p', 'g-goal__title', 'برای کدام آزمون می‌خوانی؟'));
        const row = node('div', 'g-goal__row');
        row.append(option('همه‌ی آزمون‌ها', current.type === '', () => choose({ ...ALL_EXAMS })));
        for (const type of types) {
            row.append(option(type.name, current.type === type.key, () => choose({ type: type.key, specialty: '' })));
        }
        panel.append(row);
        if (specialtiesOf(current.type).length > 0) {
            panel.append(node('p', 'g-goal__title', 'رشته (اختیاری)'));
            const list = node('div', 'g-goal__row');
            list.append(option('همه‌ی رشته‌ها', current.specialty === '', () => choose({ ...current, specialty: '' })));
            for (const specialty of specialtiesOf(current.type)) {
                list.append(option(specialty.name, current.specialty === specialty.key, () => choose({ ...current, specialty: specialty.key })));
            }
            panel.append(list);
        }
        panel.append(node('p', 'g-goal__note', 'فقط ترتیب و فیلتر پیش‌فرض را عوض می‌کند؛ همه‌چیز همیشه در دسترس است.'));
    };

    const close = () => {
        panel.hidden = true;
        chip.setAttribute('aria-expanded', 'false');
    };
    chip.addEventListener('click', () => {
        panel.hidden = !panel.hidden;
        chip.setAttribute('aria-expanded', String(!panel.hidden));
    });
    document.addEventListener('click', (event) => {
        if (!wrap.contains(event.target)) close();
    });
    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape' && !panel.hidden) {
            close();
            chip.focus();
        }
    });

    label();
    draw();
    wrap.append(chip, panel);
    return wrap;
}
