/*
 * Applying a preference to the page -- the only file in the settings trio
 * that touches the DOM or the audio hardware. runner-settings.js stays pure
 * so its rules are testable; this is where those rules become a CSS custom
 * property, a data-theme attribute, or a tone.
 */

/**
 * Both font-size sliders write one custom property each on the document
 * root, matching the task: components read the property, nothing is
 * restyled individually.
 */
export function applyFontSize(settings) {
    const root = document.documentElement;
    root.style.setProperty('--x-question-font-scale', `${settings.fontSize.question / 100}`);
    root.style.setProperty('--x-explanation-font-scale', `${settings.fontSize.explanation / 100}`);
}

/**
 * The site already themes from prefers-color-scheme plus a data-theme
 * attribute (assets/web/foundation/tokens.css); this sets that attribute
 * and nothing else. 'system' means "no explicit choice", so the attribute
 * is removed and the CSS media query decides.
 */
export function applyTheme(settings) {
    const root = document.documentElement;
    if (settings.theme === 'light' || settings.theme === 'dark') {
        root.setAttribute('data-theme', settings.theme);
    } else {
        root.removeAttribute('data-theme');
    }
}

let audioContext = null;

function context() {
    if (audioContext) return audioContext;
    const Ctor = window.AudioContext || window.webkitAudioContext;
    if (!Ctor) return null;
    audioContext = new Ctor();
    return audioContext;
}

/**
 * A short tone on a correct or incorrect answer, synthesised with the Web
 * Audio API rather than shipped as a file -- there is no build step here and
 * a sound asset is not worth one. Silent unless the student turned it on,
 * and callers never invoke this in حالت آزمون at all (see runner.js).
 */
export function playFeedbackTone(settings, kind) {
    if (!settings.sound) return;
    const ctx = context();
    if (!ctx) return;
    const oscillator = ctx.createOscillator();
    const gain = ctx.createGain();
    oscillator.type = 'sine';
    // A correct answer rings a little higher than an incorrect one, and
    // incorrect resolves downward -- a small, common "wrong buzzer" shape.
    const now = ctx.currentTime;
    if (kind === 'correct') {
        oscillator.frequency.setValueAtTime(880, now);
    } else {
        oscillator.frequency.setValueAtTime(392, now);
        oscillator.frequency.exponentialRampToValueAtTime(261, now + 0.18);
    }
    gain.gain.setValueAtTime(0.0001, now);
    gain.gain.exponentialRampToValueAtTime(0.2, now + 0.02);
    gain.gain.exponentialRampToValueAtTime(0.0001, now + 0.22);
    oscillator.connect(gain);
    gain.connect(ctx.destination);
    oscillator.start(now);
    oscillator.stop(now + 0.24);
}
