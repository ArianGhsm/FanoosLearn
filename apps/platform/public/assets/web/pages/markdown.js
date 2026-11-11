import { faOptionLetters, faText } from './runner-view.js';
import { EMPHASIS, inlineRuns, isTableRule, tableCells } from './markdown-rules.js';

/*
 * A deliberately tiny Markdown renderer for explanation and lesson text.
 *
 * Explanations arrive as Markdown (headings, bold, lists) and are the most
 * valuable thing a student reads after an exam, so rendering them flat loses
 * the structure that makes them readable. But this text comes from the API
 * and is therefore untrusted input: it is rendered by building DOM nodes and
 * setting textContent, never by assigning innerHTML. A Markdown library that
 * emits an HTML string is exactly the wrong tool here.
 *
 * Supported, because that is what the content actually uses: `## heading`,
 * `**bold**`, `- item` / `* item`, blank-line-separated paragraphs, tables
 * written `| a | b |` (a `|---|` line makes the first row the header), and
 * semantic emphasis `{{term:…}}`, `{{key:…}}`, `{{note:…}}`, `{{warn:…}}`
 * (markdown-rules.js). Anything else renders as its literal text rather
 * than disappearing.
 */

const fa = (text) => faText(faOptionLetters(text));

/** Appends a line's text, bold and emphasis runs to target. */
function inline(target, text) {
    for (const run of inlineRuns(text)) {
        if (run.kind === 'text') {
            target.append(document.createTextNode(fa(run.text)));
            continue;
        }
        const strong = document.createElement('strong');
        if (run.kind !== 'bold') {
            strong.className = `x-em x-em--${run.kind}`;
            const label = document.createElement('span');
            label.className = 'x-sr';
            label.textContent = `${EMPHASIS[run.kind]}: `;
            strong.append(label);
        }
        strong.append(document.createTextNode(fa(run.text)));
        target.append(strong);
    }
    return target;
}

function table(rows) {
    const wrap = document.createElement('div');
    wrap.className = 'x-md__table';
    const node = document.createElement('table');
    const hasHead = rows.length > 1 && isTableRule(rows[1]);
    const width = Math.max(...rows.filter((r) => !isTableRule(r)).map((r) => r.length));
    const row = (cells, tag) => {
        const tr = document.createElement('tr');
        for (let i = 0; i < width; i += 1) tr.append(inline(document.createElement(tag), cells[i] ?? ''));
        return tr;
    };
    if (hasHead) {
        const thead = document.createElement('thead');
        thead.append(row(rows[0], 'th'));
        node.append(thead);
    }
    const tbody = document.createElement('tbody');
    for (const cells of rows.slice(hasHead ? 2 : 0)) {
        if (!isTableRule(cells)) tbody.append(row(cells, 'td'));
    }
    node.append(tbody);
    wrap.append(node);
    return wrap;
}

export function renderMarkdown(source) {
    const root = document.createElement('div');
    root.className = 'x-md';
    const lines = String(source || '').replace(/\r\n/g, '\n').split('\n');

    let paragraph = null;
    let list = null;
    let rows = null;

    const closeParagraph = () => {
        if (paragraph) root.append(paragraph);
        paragraph = null;
    };
    const closeList = () => {
        if (list) root.append(list);
        list = null;
    };
    const closeTable = () => {
        if (rows) root.append(table(rows));
        rows = null;
    };

    for (const raw of lines) {
        const line = raw.trim();

        const cells = tableCells(line);
        if (cells) {
            closeParagraph();
            closeList();
            if (!rows) rows = [];
            rows.push(cells);
            continue;
        }
        closeTable();

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
    closeTable();
    return root;
}
