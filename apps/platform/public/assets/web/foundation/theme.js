/*
 * The header's light/dark switch. The site is light unless the viewer chose
 * dark; the choice is kept in this browser ("fanoos.theme") and applied by a
 * line at the top of every page before anything is drawn (PageRenderer), so
 * this only flips it and remembers.
 */
const button = document.getElementById('f-theme');

function current() {
    const set = document.documentElement.getAttribute('data-theme');
    if (set === 'dark' || set === 'light') return set;
    return window.matchMedia?.('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
}

function show() {
    button?.setAttribute('aria-pressed', current() === 'dark' ? 'true' : 'false');
}

button?.addEventListener('click', () => {
    const next = current() === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', next);
    try {
        localStorage.setItem('fanoos.theme', next);
    } catch {
        // Private mode: the switch still works for this page.
    }
    show();
});

show();
