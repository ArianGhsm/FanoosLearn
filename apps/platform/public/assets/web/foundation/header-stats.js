/*
 * The header's points and coins (امتیاز امروز، سکه), on every page of a
 * workspace: one small read (GET .../points/today), kept for a minute in
 * this tab so moving between pages does not ask again each time. A page
 * that changes them (the end of an exam, buying a box) shows the fresh
 * numbers on the next minute or the next visit.
 */
import { api } from './api.js';

const box = document.getElementById('f-stats');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';
const SLOT = `fanoos.stats.${workspaceId}`;
const FRESH_MS = 60_000;
const faDigits = (value) => String(value).replace(/[0-9]/g, (d) => '۰۱۲۳۴۵۶۷۸۹'[Number(d)]);

function show(stats) {
    document.getElementById('f-stats-points').textContent = faDigits(stats.points);
    document.getElementById('f-stats-coins').textContent = faDigits(stats.coins);
    box.setAttribute('aria-label', `امروز ${faDigits(stats.points)} از ${faDigits(stats.goal)} امتیاز، ${faDigits(stats.coins)} سکه`);
    box.dataset.done = stats.points >= stats.goal ? 'true' : 'false';
    box.hidden = false;
}

async function load() {
    try {
        const kept = JSON.parse(sessionStorage.getItem(SLOT) || 'null');
        if (kept && Date.now() - kept.at < FRESH_MS) {
            show(kept.stats);
            return;
        }
    } catch {
        // Nothing kept; ask.
    }
    try {
        const stats = await api.get(`/workspaces/${encodeURIComponent(workspaceId)}/points/today`);
        show(stats);
        try {
            sessionStorage.setItem(SLOT, JSON.stringify({ at: Date.now(), stats }));
        } catch {
            // Private mode: just not kept.
        }
    } catch {
        // The header works without it.
    }
}

if (box && workspaceId) load();
