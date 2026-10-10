/*
 * Pure rules of the visuals pages (tests/web/visuals_rules_test.mjs): the
 * small markdown subset capsules and tables are written in, read into
 * blocks the page draws with textContent only -- never HTML.
 *
 *   # / ## / ### heading      - or * bullet      1. numbered
 *   | a | b | table row (a |---| row under the first marks it a header)
 *   **bold** inside a line    a blank line ends a paragraph
 */

/** A line's inline parts: [{text, bold}]. */
export function inline(text) {
    const parts = [];
    const pattern = /\*\*([^*]+)\*\*/g;
    let last = 0;
    for (const match of String(text).matchAll(pattern)) {
        if (match.index > last) parts.push({ text: text.slice(last, match.index), bold: false });
        parts.push({ text: match[1], bold: true });
        last = match.index + match[0].length;
    }
    if (last < text.length) parts.push({ text: text.slice(last), bold: false });
    return parts;
}

const cells = (line) => line.trim().replace(/^\||\|$/g, '').split('|').map((cell) => cell.trim());

/** The markdown as blocks: heading, paragraph, list (ordered or not), table. */
export function parseMarkdown(source) {
    const blocks = [];
    const lines = String(source ?? '').replace(/\r\n?/g, '\n').split('\n');
    let i = 0;
    while (i < lines.length) {
        const line = lines[i];
        if (line.trim() === '') {
            i += 1;
            continue;
        }
        const heading = line.match(/^(#{1,3})\s+(.*)$/);
        if (heading) {
            blocks.push({ type: 'heading', level: heading[1].length, text: heading[2].trim() });
            i += 1;
            continue;
        }
        if (/^\s*\|/.test(line)) {
            const rows = [];
            let header = false;
            while (i < lines.length && /^\s*\|/.test(lines[i])) {
                if (/^\s*\|?\s*:?-{2,}/.test(lines[i].replace(/\|/g, ' | ').trim().replace(/^\|\s*/, ''))) {
                    header = rows.length === 1;
                } else {
                    rows.push(cells(lines[i]));
                }
                i += 1;
            }
            blocks.push({ type: 'table', header, rows });
            continue;
        }
        const bullet = /^\s*[-*]\s+(.*)$/;
        const numbered = /^\s*\d+[.)]\s+(.*)$/;
        if (bullet.test(line) || numbered.test(line)) {
            const ordered = numbered.test(line) && !bullet.test(line);
            const pattern = ordered ? numbered : bullet;
            const items = [];
            while (i < lines.length && pattern.test(lines[i])) {
                items.push(lines[i].match(pattern)[1].trim());
                i += 1;
            }
            blocks.push({ type: 'list', ordered, items });
            continue;
        }
        const paragraph = [];
        while (i < lines.length && lines[i].trim() !== '' && !/^(#{1,3})\s|^\s*\||^\s*[-*]\s|^\s*\d+[.)]\s/.test(lines[i])) {
            paragraph.push(lines[i].trim());
            i += 1;
        }
        blocks.push({ type: 'paragraph', text: paragraph.join(' ') });
    }
    return blocks;
}

/** How many nodes a mind-map outline has, and how deep it goes. */
export function treeSize(node, depth = 1) {
    const children = Array.isArray(node?.children) ? node.children : [];
    return children.reduce((acc, child) => {
        const sub = treeSize(child, depth + 1);
        return { nodes: acc.nodes + sub.nodes, depth: Math.max(acc.depth, sub.depth) };
    }, { nodes: 1, depth });
}

export const KIND_LABELS = Object.freeze({
    mindmap: 'نقشهٔ ذهنی',
    flowchart: 'فلوچارت',
    diagram: 'دیاگرام',
    table: 'جدول',
    capsule: 'کپسول',
});
