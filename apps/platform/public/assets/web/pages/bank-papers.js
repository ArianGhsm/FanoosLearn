/*
 * The bank's published papers as cards, one block per exam and year (a
 * residency paper alone, a specialty exam's papers side by side), shared
 * by the bank's «به تفکیک آزمون و سال» view and the exams page. Styles are
 * in bank.css; every string goes in through textContent.
 */
import { faDigits } from './question-stats.js';
import { sittingLabel } from './bank-rules.js';

/* The information hues (foundation/tokens.css), cycled across the cards. */
export const CARD_HUES = ['subject', 'accent', 'tag', 'difficulty', 'source', 'success', 'info'];

function el(tag, className, text) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
}

/** One exam's papers of one year: a heading and a card per paper. */
export function paperGroup(group) {
    const section = el('section', 'b-papers');
    section.append(el('h2', 'b-papers__title', `${group.type} ${faDigits(group.year)}`));
    const list = el('div', 'b-cards');
    group.sittings.forEach((sitting, index) => {
        // The year is the block's heading; a card names its paper (a specialty, or the exam itself).
        const card = el('a', 'b-card b-card--paper');
        card.href = `/app/exams/${encodeURIComponent(sitting.assessment_id)}`;
        card.dataset.hue = CARD_HUES[index % CARD_HUES.length];
        card.append(el('span', 'b-card__mark', String(sitting.subject_name ?? sitting.type).trim().charAt(0)));
        card.append(el('strong', 'b-card__title', sitting.subject_name ?? sittingLabel(sitting, faDigits)));
        card.append(el('span', 'b-card__count', `${faDigits(sitting.questions)} سؤال`));
        list.append(card);
    });
    section.append(list);
    return section;
}
