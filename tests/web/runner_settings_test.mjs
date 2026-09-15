/*
 * The exam runner's per-browser settings: every rule about what a setting
 * defaults to, how it round-trips through localStorage, and how a rebound
 * or cleared keyboard shortcut resolves lives here with no DOM involved, so
 * it is tested directly rather than through the page.
 */
import { test } from 'node:test';
import assert from 'node:assert/strict';

function fakeStorage() {
    const data = new Map();
    return {
        getItem(key) { return data.has(key) ? data.get(key) : null; },
        setItem(key, value) { data.set(key, String(value)); },
        removeItem(key) { data.delete(key); },
    };
}

function throwingStorage() {
    return {
        get getItem() { throw new Error('SecurityError: storage disabled in a private window'); },
        get setItem() { throw new Error('SecurityError: storage disabled in a private window'); },
    };
}

globalThis.window = globalThis.window ?? {};
window.localStorage = fakeStorage();

const S = await import('../../apps/platform/public/assets/web/pages/runner-settings.js');

test('every setting round-trips through storage', () => {
    window.localStorage = fakeStorage();
    let settings = S.defaultSettings();
    settings = S.setFontSize(settings, 'question', 140);
    settings = S.setFontSize(settings, 'explanation', 90);
    settings = S.setTheme(settings, 'dark');
    settings = S.setSound(settings, true);
    settings = S.setSpeed(settings, 'fast');
    settings = S.bindShortcut(settings, 'flag', 'g');
    settings = S.clearShortcut(settings, 'map');
    settings = S.setNavigation(settings, 'swipe', false);

    assert.equal(S.saveSettings(settings), true);
    const loaded = S.loadSettings();

    assert.equal(loaded.fontSize.question, 140);
    assert.equal(loaded.fontSize.explanation, 90);
    assert.equal(loaded.theme, 'dark');
    assert.equal(loaded.sound, true);
    assert.equal(loaded.speed, 'fast');
    assert.equal(loaded.shortcuts.flag, 'g');
    assert.equal(loaded.shortcuts.map, null, 'a cleared shortcut must stay cleared across reload, not silently revert to the default');
    assert.equal(loaded.navigation.swipe, false);
    assert.equal(loaded.navigation.horizontalScroll, true, 'an untouched navigation toggle keeps its default');
});

test('the runner renders correctly when storage throws instead of returning null', () => {
    // A private window throws on `localStorage` access itself, not only on a
    // missing key -- loadSettings must still hand back a usable default
    // rather than letting the exception reach the caller.
    window.localStorage = throwingStorage();
    const settings = S.loadSettings();
    assert.deepEqual(settings, S.defaultSettings());

    // A write that cannot land must say so rather than pretending to have
    // saved.
    assert.equal(S.saveSettings(settings), false);
});

test('a corrupted or half-written stored value is repaired field by field', () => {
    window.localStorage = fakeStorage();
    window.localStorage.setItem('fanoos.examRunner.settings.v1', JSON.stringify({
        fontSize: { question: 999, explanation: 'not a number' },
        theme: 'ultraviolet',
        speed: 'ludicrous',
        shortcuts: { flag: 123, next: 'q' },
    }));

    const settings = S.loadSettings();
    assert.equal(settings.fontSize.question, S.FONT_MAX, 'an out-of-range value is clamped rather than accepted');
    assert.equal(settings.fontSize.explanation, 100, 'a non-numeric value falls back to the default');
    assert.equal(settings.theme, 'system', 'an invalid theme falls back to the default');
    assert.equal(settings.speed, 'normal', 'an invalid speed falls back to the default');
    assert.equal(settings.shortcuts.flag, 'f', 'a non-string shortcut value is ignored, keeping the default');
    assert.equal(settings.shortcuts.next, 'q', 'a valid rebound shortcut is still honoured');
});

test('rebinding a key takes it from whatever action already held it', () => {
    let settings = S.defaultSettings();
    // 'm' already opens the map by default; binding it to "flag" must move
    // it, not duplicate it, or both rows would fire on the same press.
    settings = S.bindShortcut(settings, 'flag', 'm');
    assert.equal(settings.shortcuts.flag, 'm');
    assert.equal(settings.shortcuts.map, null);
    assert.equal(S.actionForKey(settings.shortcuts, 'm'), 'flag');
});

test('a cleared shortcut stops firing and resetShortcuts restores every default', () => {
    let settings = S.defaultSettings();
    settings = S.clearShortcut(settings, 'flag');
    assert.equal(S.actionForKey(settings.shortcuts, 'f'), null, 'a cleared shortcut must not still resolve to its action');

    settings = S.bindShortcut(settings, 'next', 'j');
    settings = S.resetShortcuts(settings);
    assert.deepEqual(settings.shortcuts, S.DEFAULT_SHORTCUTS);
});

test('letter shortcuts bind case-insensitively; named keys keep their spelling', () => {
    let settings = S.defaultSettings();
    settings = S.bindShortcut(settings, 'flag', 'G');
    assert.equal(settings.shortcuts.flag, 'g');
    assert.equal(S.actionForKey(settings.shortcuts, 'g'), 'flag');
    assert.equal(S.actionForKey(settings.shortcuts, 'G'), 'flag');
    assert.equal(S.actionForKey(settings.shortcuts, 'ArrowLeft'), 'next');
});

test('turbo speed only takes effect in learning mode elsewhere it behaves as normal', () => {
    const settings = S.setSpeed(S.defaultSettings(), 'turbo');
    assert.equal(S.effectiveSpeed(settings, 'learning'), 'turbo');
    assert.equal(S.effectiveSpeed(settings, 'practice'), 'normal');
    assert.equal(S.effectiveSpeed(settings, 'assessment'), 'normal');
    assert.equal(S.effectiveSpeed(settings, null), 'normal');
});

test('shortcuts are only live on a question, with no dialog open, and not while typing', () => {
    assert.equal(S.shortcutsAreLive({ phase: 'question', hasDialog: false }), true);
    assert.equal(S.shortcutsAreLive({ phase: 'intro', hasDialog: false }), false, 'shortcuts must not fire outside the question phase');
    assert.equal(S.shortcutsAreLive({ phase: 'question', hasDialog: true }), false, 'shortcuts must not fire while a dialog (map, submit, settings) is open');
    assert.equal(S.shortcutsAreLive({ phase: 'question', hasDialog: false, targetTagName: 'INPUT' }), false);
    assert.equal(S.shortcutsAreLive({ phase: 'question', hasDialog: false, targetTagName: 'TEXTAREA' }), false);
    assert.equal(S.shortcutsAreLive({ phase: 'question', hasDialog: false, targetTagName: 'SELECT' }), false);
    assert.equal(S.shortcutsAreLive({ phase: 'question', hasDialog: false, isContentEditable: true }), false);
    assert.equal(S.shortcutsAreLive({ phase: 'question', hasDialog: false, targetTagName: 'BUTTON' }), true);
});

test('font size steps clamp to the documented range', () => {
    let settings = S.defaultSettings();
    for (let i = 0; i < 20; i++) settings = S.stepFontSize(settings, 'question', 1);
    assert.equal(settings.fontSize.question, S.FONT_MAX);
    for (let i = 0; i < 20; i++) settings = S.stepFontSize(settings, 'question', -1);
    assert.equal(settings.fontSize.question, S.FONT_MIN);
});
