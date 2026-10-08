/*
 * مرور اشتباه‌ها: reads ExamService::mistakesReview() and lists every
 * question this student has answered incorrectly, across whichever scored
 * attempts produced it. Read-only review, same trust level as the report's
 * own review screen -- nothing here is an in-progress attempt, so nothing
 * is paced.
 */
import { api, describeError } from '../foundation/api.js';
import { bidiNodes, el, faDigits, faText, notice, questionImageUrl, renderFigure } from './runner-view.js';
import { renderMarkdown } from './markdown.js';

const CHOICE_LETTERS = ['الف', 'ب', 'ج', 'د', 'ه', 'و', 'ز', 'ح', 'ط', 'ی'];

const root = document.getElementById('mistakes');
const workspaceId = document.querySelector('meta[name="fanoos-workspace"]')?.content ?? '';

function card(question) {
    const choices = el('ul', { className: 'x-choices x-choices--review' });
    (question.choices || []).forEach((choice, index) => {
        const isCorrect = index === question.correct || (question.also_correct || []).includes(index);
        choices.append(el('li', { className: 'x-choices__item' },
            el('div', { className: `x-choice x-choice--review${isCorrect ? ' is-correct' : ''}` },
                el('span', { className: 'x-choice__letter', text: CHOICE_LETTERS[index] ?? faDigits(index + 1) }),
                el('span', { className: 'x-choice__text', attrs: { dir: 'auto' } }, ...bidiNodes(faText(choice)),
                    question.images?.choices?.[index]
                        ? renderFigure(questionImageUrl(question.assessment_id, question.question_id, `choice-${index}`), `تصویر گزینه‌ی ${CHOICE_LETTERS[index] ?? index + 1}`, { compact: true })
                        : null),
                isCorrect ? el('span', { className: 'x-choice__mark', text: '✓ پاسخ درست' }) : null)));
    });

    return el('article', { className: 'f-card x-review__card x-mistakes__card' },
        el('span', { className: 'x-chip x-chip--static', text: faText(question.assessment_title) }),
        el('p', { className: 'x-question__prompt', attrs: { dir: 'auto' } }, ...bidiNodes(faText(question.prompt))),
        question.images?.stem
            ? renderFigure(questionImageUrl(question.assessment_id, question.question_id, 'stem'), 'تصویر سؤال')
            : null,
        choices,
        question.explanation
            ? el('section', { className: 'x-explanation' },
                el('h3', { text: 'چرا؟' }),
                renderMarkdown(question.explanation),
                el('p', { className: 'x-explanation__origin', text: 'این توضیح با کمک هوش مصنوعی نوشته شده و بازبینی انسانی نشده است.' }))
            : el('p', { className: 'f-tiny', text: 'برای این سؤال توضیحی ثبت نشده است.' }));
}

async function load() {
    root.setAttribute('aria-busy', 'true');
    try {
        const payload = await api.get(`/workspaces/${encodeURIComponent(workspaceId)}/mistakes-review`);
        const questions = Array.isArray(payload?.questions) ? payload.questions : [];
        root.setAttribute('aria-busy', 'false');
        if (questions.length === 0) {
            root.replaceChildren(el('div', { className: 'f-card f-empty' },
                el('div', { className: 'f-empty__title', text: 'هنوز اشتباهی ثبت نشده' }),
                el('p', { text: 'وقتی آزمونی را ثبت کنی و جایی را غلط بزنی، همین‌جا برای مرور می‌آید.' })));
            return;
        }
        root.replaceChildren(el('div', { className: 'x-mistakes__list' }, ...questions.map(card)));
    } catch (error) {
        root.setAttribute('aria-busy', 'false');
        root.replaceChildren(notice('error', 'مرور اشتباه‌ها خوانده نشد', describeError(error), {
            label: 'تلاش دوباره',
            onClick: load,
        }));
    }
}

if (root && workspaceId) load();
