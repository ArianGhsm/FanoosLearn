/*
 * The bank's published papers as rows (owner, 2026-11-14: «ردیفی بهتره»):
 * the exams page lists every exam type, each year one row; a year with one
 * paper (دستیاری, ملی) opens that paper, a year with a paper per specialty
 * (ارتقا, بورد) opens in place to its specialties. The bank's «به تفکیک آزمون
 * و سال» view shows one year's papers with the same rows. Styles are in
 * bank.css; every string goes in through textContent.
 */
import { faDigits } from './question-stats.js';
import { orderTypes, sittingLabel } from './bank-rules.js';

/* The information hues (foundation/tokens.css), one per exam type or paper. */
export const CARD_HUES = ['subject', 'accent', 'tag', 'difficulty', 'source', 'success', 'info'];

const TYPE_HUE = { residency: 'accent', national: 'info', promotion: 'subject', board: 'tag' };

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

function chevron() {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 24 24');
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add('b-row__chevron');
    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', 'M15 5l-7 7 7 7');
    path.setAttribute('fill', 'none');
    path.setAttribute('stroke', 'currentColor');
    path.setAttribute('stroke-width', '2');
    path.setAttribute('stroke-linecap', 'round');
    path.setAttribute('stroke-linejoin', 'round');
    svg.append(path);
    return svg;
}

const total = (sittings) => sittings.reduce((sum, s) => sum + Number(s.questions || 0), 0);

/** One paper as a row that opens it. */
function paperRow(sitting, title, hue, mark = String(title).trim().charAt(0)) {
    const row = el('a', 'b-row');
    row.href = `/app/exams/${encodeURIComponent(sitting.assessment_id)}`;
    row.dataset.hue = hue;
    row.append(
        el('span', 'b-row__mark', String(title).trim().charAt(0)),
        el('span', 'b-row__title', title),
        el('span', 'b-row__meta', `${faDigits(sitting.questions)} سؤال`),
        chevron());
    return el('li', 'b-rows__item', null).appendChild(row).parentNode;
}

/** One exam's papers of one year (the bank's year view): a heading and a row per paper. */
export function paperGroup(group) {
    const section = el('section', 'b-papers');
    section.append(el('h2', 'b-papers__title', `${group.type} ${faDigits(group.year)}`));
    const list = el('ul', 'b-rows');
    group.sittings.forEach((sitting, index) => {
        list.append(paperRow(sitting, sitting.subject_name ?? sittingLabel(sitting, faDigits), CARD_HUES[index % CARD_HUES.length]));
    });
    section.append(list);
    return section;
}

/**
 * Every exam type, newest year first, one row a year. `groups` is
 * groupSittings()'s output (one entry per type and year).
 */
export function paperIndex(groups) {
    const byType = new Map();
    for (const group of groups) {
        if (!byType.has(group.type_key)) byType.set(group.type_key, { key: group.type_key, name: group.type, years: [] });
        byType.get(group.type_key).years.push(group);
    }
    const out = document.createDocumentFragment();
    for (const type of orderTypes([...byType.values()], (t) => t.key)) {
        const hue = TYPE_HUE[type.key] ?? 'info';
        const section = el('section', 'b-papers');
        const head = el('div', 'b-papers__head');
        head.append(el('h2', 'b-papers__title', type.name), el('span', 'b-papers__count', `${faDigits(type.years.length)} سال`));
        section.append(head);
        const list = el('ul', 'b-rows');
        for (const year of type.years.sort((a, b) => b.year - a.year)) {
            const title = `${type.name} ${faDigits(year.year)}`;
            if (year.sittings.length === 1) {
                list.append(paperRow(year.sittings[0], title, hue, faDigits(String(year.year).slice(-2))));
                continue;
            }
            // A paper per specialty: the year opens in place to its specialties.
            const item = el('li', 'b-rows__item');
            const group = el('details', 'b-rowgroup');
            const summary = el('summary', 'b-row');
            summary.dataset.hue = hue;
            summary.append(
                el('span', 'b-row__mark', faDigits(String(year.year).slice(-2))),
                el('span', 'b-row__title', title),
                el('span', 'b-row__meta', `${faDigits(year.sittings.length)} رشته · ${faDigits(total(year.sittings))} سؤال`),
                chevron());
            const sub = el('ul', 'b-rows b-rows--sub');
            year.sittings.forEach((sitting, index) => {
                sub.append(paperRow(sitting, sitting.subject_name ?? sittingLabel(sitting, faDigits), CARD_HUES[index % CARD_HUES.length]));
            });
            group.append(summary, sub);
            item.append(group);
            list.append(item);
        }
        section.append(list);
        out.append(section);
    }
    return out;
}
