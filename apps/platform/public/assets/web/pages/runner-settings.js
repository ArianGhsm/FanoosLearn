/*
 * The exam runner's per-browser preferences: font size, theme, sound, pacing
 * speed, keyboard shortcuts and question-navigation gestures.
 *
 * Same split as the rest of the runner: this module is pure state, with no
 * DOM and no network in it, so every rule -- what a setting defaults to, how
 * it is validated, how a corrupted or half-written localStorage value is
 * repaired rather than crashing the page -- can be tested directly.
 * Applying a setting (a CSS custom property, a data-theme attribute, a tone)
 * lives in runner-settings-effects.js; drawing the sheet lives in
 * runner-settings-view.js.
 *
 * Nothing stored here ever reaches the server, and nothing here is allowed
 * to change scoring, timing, pacing or what content is delivered -- it is
 * read here and only here, by the runner's own wiring, same as any other
 * local, per-viewer convenience.
 */

const STORAGE_KEY = 'fanoos.examRunner.settings.v1';

export const FONT_MIN = 80;
export const FONT_MAX = 160;
export const FONT_STEP = 10;

const THEMES = ['light', 'dark', 'system'];
const SPEEDS = ['normal', 'fast', 'turbo'];

/**
 * The shortcuts runner.js already bound before this module existed: 1-9 to
 * pick a choice, the RTL-correct arrow pair, F to flag, M for the map, R to
 * reveal (learning only). Lifting them into data here is what makes them
 * rebindable instead of hardcoded.
 */
export const DEFAULT_SHORTCUTS = {
    choice_1: '1', choice_2: '2', choice_3: '3', choice_4: '4', choice_5: '5',
    choice_6: '6', choice_7: '7', choice_8: '8', choice_9: '9',
    previous: 'ArrowRight', next: 'ArrowLeft',
    flag: 'f', map: 'm', reveal: 'r',
};

export const SHORTCUT_ACTIONS = Object.keys(DEFAULT_SHORTCUTS);

export const SHORTCUT_LABELS = {
    choice_1: 'انتخاب گزینه ۱', choice_2: 'انتخاب گزینه ۲', choice_3: 'انتخاب گزینه ۳',
    choice_4: 'انتخاب گزینه ۴', choice_5: 'انتخاب گزینه ۵', choice_6: 'انتخاب گزینه ۶',
    choice_7: 'انتخاب گزینه ۷', choice_8: 'انتخاب گزینه ۸', choice_9: 'انتخاب گزینه ۹',
    previous: 'سؤال قبلی', next: 'سؤال بعدی',
    flag: 'نشان‌دار کردن سؤال', map: 'باز کردن نقشه سؤال‌ها',
    reveal: 'نمایش پاسخ (فقط حالت یادگیری)',
};

/** The three navigation gestures, each an independent on-by-default toggle. */
export const NAVIGATION_KEYS = ['horizontalScroll', 'verticalScroll', 'swipe'];

export function defaultSettings() {
    return {
        fontSize: { question: 100, explanation: 100 },
        theme: 'system',
        sound: false,
        speed: 'normal',
        shortcuts: { ...DEFAULT_SHORTCUTS },
        navigation: { horizontalScroll: true, verticalScroll: true, swipe: true },
    };
}

/**
 * Reads settings from localStorage, repairing anything missing or invalid
 * back to the default rather than letting a corrupted value reach the rest
 * of the runner. Every access is guarded: a private window throws on
 * `localStorage` itself, not only on a missing key, so the whole read has to
 * be inside the try.
 */
export function loadSettings() {
    const settings = defaultSettings();
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) return settings;
        const parsed = JSON.parse(raw);
        return mergeSettings(settings, parsed);
    } catch {
        return settings;
    }
}

/** @returns {boolean} whether the write succeeded -- callers show it did not silently. */
export function saveSettings(settings) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(settings));
        return true;
    } catch {
        return false;
    }
}

function mergeSettings(defaults, stored) {
    if (stored === null || typeof stored !== 'object') return defaults;
    let settings = defaults;
    if (stored.fontSize && typeof stored.fontSize === 'object') {
        if (Number.isFinite(stored.fontSize.question)) settings = setFontSize(settings, 'question', stored.fontSize.question);
        if (Number.isFinite(stored.fontSize.explanation)) settings = setFontSize(settings, 'explanation', stored.fontSize.explanation);
    }
    if (THEMES.includes(stored.theme)) settings = setTheme(settings, stored.theme);
    if (typeof stored.sound === 'boolean') settings = setSound(settings, stored.sound);
    if (SPEEDS.includes(stored.speed)) settings = setSpeed(settings, stored.speed);
    if (stored.shortcuts && typeof stored.shortcuts === 'object') {
        const shortcuts = { ...settings.shortcuts };
        for (const action of SHORTCUT_ACTIONS) {
            if (!(action in stored.shortcuts)) continue;
            const key = stored.shortcuts[action];
            // A stored `null` is a deliberate "cleared" binding and must
            // stay cleared; only a real key string re-binds it. Anything
            // else present-but-invalid is ignored, keeping the default.
            shortcuts[action] = key === null ? null : (typeof key === 'string' && key ? key : shortcuts[action]);
        }
        settings = { ...settings, shortcuts };
    }
    if (stored.navigation && typeof stored.navigation === 'object') {
        const navigation = { ...settings.navigation };
        for (const key of NAVIGATION_KEYS) {
            if (typeof stored.navigation[key] === 'boolean') navigation[key] = stored.navigation[key];
        }
        settings = { ...settings, navigation };
    }
    return settings;
}

export function setFontSize(settings, target, percent) {
    if (target !== 'question' && target !== 'explanation') throw new Error('invalid font size target');
    const stepped = Math.round(Number(percent) / FONT_STEP) * FONT_STEP;
    const clamped = Math.min(FONT_MAX, Math.max(FONT_MIN, Number.isFinite(stepped) ? stepped : 100));
    return { ...settings, fontSize: { ...settings.fontSize, [target]: clamped } };
}

export function stepFontSize(settings, target, direction) {
    const current = settings.fontSize[target] ?? 100;
    return setFontSize(settings, target, current + (direction < 0 ? -FONT_STEP : FONT_STEP));
}

export function setTheme(settings, theme) {
    if (!THEMES.includes(theme)) throw new Error('invalid theme');
    return { ...settings, theme };
}

export function setSound(settings, enabled) {
    return { ...settings, sound: Boolean(enabled) };
}

export function setSpeed(settings, speed) {
    if (!SPEEDS.includes(speed)) throw new Error('invalid speed');
    return { ...settings, speed };
}

/**
 * فوق‌سریع only exists in learning mode -- see runner.js's turbo-reveal
 * wiring for why. Elsewhere the stored preference is kept (so it is waiting
 * when the student starts a learning attempt again) but behaves as 'normal'
 * in the meantime; it is not silently promoted to 'fast' either, since that
 * would auto-advance in a mode the student never asked to auto-advance in.
 */
export function effectiveSpeed(settings, mode) {
    if (settings.speed === 'turbo' && mode !== 'learning') return 'normal';
    return settings.speed;
}

export function setNavigation(settings, key, enabled) {
    if (!NAVIGATION_KEYS.includes(key)) throw new Error('invalid navigation setting');
    return { ...settings, navigation: { ...settings.navigation, [key]: Boolean(enabled) } };
}

/** A single letter binds case-insensitively; named keys (ArrowLeft, Escape) keep their canonical spelling. */
export function normalizeKey(key) {
    return typeof key === 'string' && key.length === 1 ? key.toLowerCase() : key;
}

export function bindShortcut(settings, action, key) {
    if (!SHORTCUT_ACTIONS.includes(action)) throw new Error('invalid shortcut action');
    const normalized = normalizeKey(key);
    const shortcuts = { ...settings.shortcuts };
    for (const existingAction of SHORTCUT_ACTIONS) {
        // A key can bind only one action; taking it from wherever it was is
        // what keeps the table from silently holding two rows that both fire
        // on the same press.
        if (shortcuts[existingAction] === normalized) shortcuts[existingAction] = null;
    }
    shortcuts[action] = normalized;
    return { ...settings, shortcuts };
}

export function clearShortcut(settings, action) {
    if (!SHORTCUT_ACTIONS.includes(action)) throw new Error('invalid shortcut action');
    return { ...settings, shortcuts: { ...settings.shortcuts, [action]: null } };
}

export function resetShortcuts(settings) {
    return { ...settings, shortcuts: { ...DEFAULT_SHORTCUTS } };
}

/**
 * The action bound to a keydown, or null. Pure lookup -- callers still
 * decide *whether* to consult it at all (runner.js keeps the "inert while a
 * dialog is open or a field has focus" rule, since that is about DOM state,
 * not a preference).
 */
export function actionForKey(shortcuts, key) {
    const normalized = normalizeKey(key);
    for (const action of SHORTCUT_ACTIONS) {
        if (shortcuts[action] === normalized) return action;
    }
    return null;
}

/**
 * Whether a keydown should be read as a shortcut at all: only while a
 * question is showing, with no dialog open, and not while the keydown
 * landed on a field the student could be typing into. Pulled out as a pure
 * predicate (rather than left inline in runner.js) so it is testable without
 * a DOM -- a shortcut firing while someone is typing is worse than no
 * shortcut.
 */
export function shortcutsAreLive({ phase, hasDialog, targetTagName = null, isContentEditable = false }) {
    if (phase !== 'question' || hasDialog) return false;
    if (isContentEditable) return false;
    if (targetTagName && ['INPUT', 'TEXTAREA', 'SELECT'].includes(targetTagName)) return false;
    return true;
}
