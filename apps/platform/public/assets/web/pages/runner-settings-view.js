/*
 * Rendering for the settings sheet. Same rule as runner-view.js: pure DOM
 * construction from state, no network, no mutation -- every handler is
 * passed in through `actions`.
 */
import { FONT_MAX, FONT_MIN, SHORTCUT_ACTIONS, SHORTCUT_LABELS } from './runner-settings.js';
import { el, faDigits } from './runner-view.js';

const THEME_OPTIONS = [
    { value: 'light', label: 'روشن' },
    { value: 'dark', label: 'تیره' },
    { value: 'system', label: 'سیستم' },
];

const NAVIGATION_TOGGLES = [
    { key: 'horizontalScroll', label: 'اسکرول افقی روی سؤال', hint: 'چرخ ماوس یا تِرک‌پد را روی کارت سؤال به چپ و راست بچرخان.' },
    { key: 'verticalScroll', label: 'اسکرول عمودی کنار سؤال', hint: 'چرخ ماوس را در حاشیه‌ی کنار کارت، نه روی خودش، بچرخان.' },
    { key: 'swipe', label: 'سوایپ لمسی روی سؤال', hint: 'روی صفحه‌ی لمسی، روی کارت سؤال به چپ یا راست بکش.' },
];

/**
 * @param {object} settings
 * @param {{tab: 'general'|'advanced', rebinding: string|null}} ui
 * @param {string|null} mode the attempt's current mode, or null before one starts -- governs whether فوق‌سریع is offered at all.
 */
export function renderSettings(settings, ui, mode, actions) {
    const body = ui.tab === 'advanced'
        ? renderAdvancedTab(settings, ui, actions)
        : renderGeneralTab(settings, mode, actions);

    return el('div', { className: 'x-dialog', attrs: { role: 'dialog', 'aria-modal': 'true', 'aria-label': 'تنظیمات', tabindex: '-1' } },
        el('div', { className: 'x-dialog__panel x-settings__panel' },
            el('header', { className: 'x-dialog__head' },
                el('h2', { text: 'تنظیمات' }),
                el('button', { className: 'x-dialog__close', type: 'button', text: '✕', attrs: { 'aria-label': 'بستن' }, on: { click: actions.close } })),
            el('div', { className: 'x-settings__tabs', attrs: { role: 'tablist' } },
                tabButton('general', 'عمومی', ui.tab, actions.setTab),
                tabButton('advanced', 'پیشرفته', ui.tab, actions.setTab)),
            body,
            el('p', { className: 'f-tiny x-settings__note', text: 'تغییرات خودکار در این مرورگر ذخیره می‌شوند.' })));
}

function tabButton(value, label, current, onSelect) {
    return el('button', {
        className: `x-settings__tab-btn${current === value ? ' is-active' : ''}`, type: 'button', text: label,
        attrs: { role: 'tab', 'aria-selected': current === value ? 'true' : 'false' },
        on: { click: () => onSelect(value) },
    });
}

function renderGeneralTab(settings, mode, actions) {
    return el('div', { className: 'x-settings__tab-panel', attrs: { role: 'tabpanel' } },
        el('section', { className: 'x-settings__section' },
            el('h3', { text: 'اندازه فونت' }),
            fontSlider('سؤال', 'question', settings.fontSize.question, actions),
            fontSlider('توضیح', 'explanation', settings.fontSize.explanation, actions)),
        el('section', { className: 'x-settings__section' },
            el('h3', { text: 'تم' }),
            segmented('تم', THEME_OPTIONS, settings.theme, actions.setTheme)),
        el('section', { className: 'x-settings__section' },
            el('h3', { text: 'صدا' }),
            toggle('پخش صدا برای پاسخ درست و نادرست', settings.sound, actions.setSound),
            el('p', { className: 'f-tiny', text: 'در حالت آزمون هیچ صدایی پخش نمی‌شود، حتی اگر روشن باشد.' })),
        el('section', { className: 'x-settings__section' },
            el('h3', { text: 'سرعت' }),
            segmented('سرعت', speedOptions(mode), settings.speed, actions.setSpeed),
            speedNote(settings.speed, mode)));
}

function speedOptions(mode) {
    const options = [
        { value: 'normal', label: 'عادی' },
        { value: 'fast', label: 'سریع' },
    ];
    if (mode === 'learning') options.push({ value: 'turbo', label: 'فوق‌سریع' });
    return options;
}

function speedNote(speed, mode) {
    if (speed === 'turbo' && mode !== 'learning') {
        return el('p', { className: 'f-tiny', text: 'فوق‌سریع فقط در حالت یادگیری در دسترس است؛ تا آن‌جا برگردی، سرعت عادی اعمال می‌شود.' });
    }
    const text = {
        normal: 'خودت پاسخ را می‌دهی، توضیح را می‌خوانی و وقتی خواستی ادامه می‌دهی.',
        fast: 'بعد از پاسخ درست، کمی بعد خودکار به سؤال بعد می‌روی. پاسخ نادرست همیشه متوقف می‌شود تا بخوانی.',
        turbo: 'پاسخ درست هر سؤال، همان لحظه‌ی ورود، نشان داده می‌شود -- فقط یک سؤال در هر لحظه، بدون پیش‌واکشی.',
    }[speed] ?? '';
    return text ? el('p', { className: 'f-tiny', text }) : null;
}

/**
 * A full redraw on every `input` tick would replace the range element mid
 * drag and break the gesture, so dragging only paints a live preview
 * (percentage label + the CSS variable, via actions.previewFont) and the
 * settled value commits -- persisted, clamped, redrawn -- on `change`.
 */
function fontSlider(label, target, value, actions) {
    const valueLabel = el('span', { className: 'x-settings__value', text: `٪${faDigits(value)}`, attrs: { role: 'status' } });
    const input = el('input', {
        type: 'range',
        attrs: { min: FONT_MIN, max: FONT_MAX, step: 10, value, 'aria-label': `اندازه فونت ${label}` },
        on: {
            input: (event) => {
                const percent = Number(event.target.value);
                valueLabel.textContent = `٪${faDigits(percent)}`;
                actions.previewFont(target, percent);
            },
            change: (event) => actions.setFont(target, Number(event.target.value)),
        },
    });

    return el('div', { className: 'x-settings__row' },
        el('div', { className: 'x-settings__row-head' }, el('span', { text: label }), valueLabel),
        el('div', { className: 'x-settings__slider' },
            el('button', {
                className: 'x-stepper__btn', type: 'button', text: '−',
                attrs: { 'aria-label': `کم کردن اندازه فونت ${label}` },
                on: { click: () => actions.stepFont(target, -1) },
            }),
            input,
            el('button', {
                className: 'x-stepper__btn', type: 'button', text: '+',
                attrs: { 'aria-label': `زیاد کردن اندازه فونت ${label}` },
                on: { click: () => actions.stepFont(target, 1) },
            })));
}

function segmented(name, options, current, onSelect) {
    const group = el('div', { className: 'x-segmented', attrs: { role: 'radiogroup', 'aria-label': name } });
    for (const option of options) {
        group.append(el('button', {
            className: `x-segmented__btn${current === option.value ? ' is-active' : ''}`, type: 'button', text: option.label,
            attrs: { role: 'radio', 'aria-checked': current === option.value ? 'true' : 'false' },
            on: { click: () => onSelect(option.value) },
        }));
    }
    return group;
}

function toggle(label, checked, onToggle, hint = null) {
    return el('div', { className: 'x-settings__row x-settings__row--toggle' },
        el('div', {},
            el('span', { text: label }),
            hint ? el('p', { className: 'f-tiny', text: hint }) : null),
        el('button', {
            className: `x-toggle${checked ? ' is-on' : ''}`, type: 'button',
            attrs: { role: 'switch', 'aria-checked': checked ? 'true' : 'false', 'aria-label': label },
            on: { click: () => onToggle(!checked) },
        }, el('span', { className: 'x-toggle__thumb' })));
}

function renderAdvancedTab(settings, ui, actions) {
    const rows = el('div', { className: 'x-shortcuts', attrs: { role: 'table', 'aria-label': 'میانبرهای کیبورد' } });
    for (const action of SHORTCUT_ACTIONS) {
        rows.append(shortcutRow(action, settings.shortcuts[action], ui.rebinding === action, actions));
    }

    return el('div', { className: 'x-settings__tab-panel', attrs: { role: 'tabpanel' } },
        el('section', { className: 'x-settings__section' },
            el('div', { className: 'x-settings__section-head' },
                el('h3', { text: 'میانبرهای کیبورد' }),
                el('button', {
                    className: 'f-btn f-btn--ghost', type: 'button', text: 'بازگردانی پیش‌فرض‌ها',
                    on: { click: actions.resetShortcuts },
                })),
            el('p', { className: 'f-tiny', text: 'روی یک ردیف بزن، بعد کلید تازه را فشار بده تا جایگزین شود. وقتی کادری باز است یا روی یک فیلد هستی، میانبرها غیرفعال‌اند.' }),
            rows),
        el('section', { className: 'x-settings__section' },
            el('h3', { text: 'پیمایش سؤال‌ها' }),
            ...NAVIGATION_TOGGLES.map((item) => toggle(item.label, settings.navigation[item.key], (value) => actions.setNavigation(item.key, value), item.hint))));
}

function shortcutRow(action, key, isListening, actions) {
    const label = SHORTCUT_LABELS[action];
    const keyLabel = isListening ? 'کلیدی را بزن…' : (key ? displayKey(key) : 'تنظیم‌نشده');
    return el('div', { className: `x-shortcuts__row${isListening ? ' is-listening' : ''}`, attrs: { role: 'row' } },
        el('span', { className: 'x-shortcuts__label', attrs: { role: 'cell' }, text: label }),
        el('button', {
            className: 'x-shortcuts__key', type: 'button', text: keyLabel, attrs: { role: 'cell', 'aria-label': `تغییر میانبر ${label}` },
            on: { click: () => actions.startRebind(action) },
        }),
        el('span', { className: 'x-shortcuts__clear-slot', attrs: { role: 'cell' } },
            key ? el('button', {
                className: 'x-shortcuts__clear', type: 'button', text: 'حذف', attrs: { 'aria-label': `حذف میانبر ${label}` },
                on: { click: () => actions.clearShortcut(action) },
            }) : null));
}

function displayKey(key) {
    if (key === 'ArrowLeft') return '←';
    if (key === 'ArrowRight') return '→';
    if (/^[0-9]$/.test(key)) return faDigits(key);
    return key.length === 1 ? key.toUpperCase() : key;
}
