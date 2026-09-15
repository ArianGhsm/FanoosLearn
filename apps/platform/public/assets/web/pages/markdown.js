import { faText } from './runner-view.js';

/*
 * A deliberately tiny Markdown renderer for explanation text.
 *
 * Explanations arrive as Markdown (headings, bold, lists) and are the most
 * valuable thing a student reads after an exam, so rendering them flat loses
 * the structure that makes them readable. But this text comes from the API
 * and is therefore untrusted input: it is rendered by building DOM nodes and
 * setting textContent, never by assigning innerHTML. A Markdown library that
 * emits an HTML string is exactly the wrong tool here.
 *
 * Supported, because that is what the content actually uses: `## heading`,
 * `**bold**`, `- item` / `* item`, and blank-line-separated paragraphs.
 * Anything else renders as its literal text rather than disappearing.
 */

/** Splits a line into text and bold runs. */
function inline(target, text) {
    const pattern = /\*\*([^*]+)\*\*/g;
    let index = 0;
    let match;
    while ((match = pattern.exec(text)) !== null) {
        if (match.index > index) {
            target.append(document.createTextNode(faText(text.slice(index, match.index))));
        }
        const strong = document.createElement('strong');
        strong.textContent = faText(match[1]);
        target.append(strong);
        index = match.index + match[0].length;
    }
    if (index < text.length) {
        target.append(document.createTextNode(faText(text.slice(index))));
    }
    return target;
}

export function renderMarkdown(source) {
    const root = document.createElement('div');
    root.className = 'x-md';
    const lines = String(source || '').replace(/\r\n/g, '\n').split('\n');

    let paragraph = null;
    let list = null;

    const closeParagraph = () => {
        if (paragraph) root.append(paragraph);
        paragraph = null;
    };
    const closeList = () => {
        if (list) root.append(list);
        list = null;
    };

    for (const raw of lines) {
        const line = raw.trim();

        if (line === '') {
            closeParagraph();
            closeList();
            continue;
        }

        const heading = /^(#{1,6})\s+(.*)$/.exec(line);
        if (heading) {
            closeParagraph();
            closeList();
            // Explanations sit inside a card that already has an <h3>, so the
            // document outline stays sane if these never go above h4.
            const level = Math.min(6, 3 + heading[1].length);
            root.append(inline(document.createElement(`h${level}`), heading[2]));
            continue;
        }

        const item = /^[-*]\s+(.*)$/.exec(line);
        if (item) {
            closeParagraph();
            if (!list) list = document.createElement('ul');
            list.append(inline(document.createElement('li'), item[1]));
            continue;
        }

        closeList();
        if (!paragraph) {
            paragraph = document.createElement('p');
        } else {
            paragraph.append(document.createTextNode(' '));
        }
        inline(paragraph, line);
    }

    closeParagraph();
    closeList();
    return root;
}
