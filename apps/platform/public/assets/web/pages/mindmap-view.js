/*
 * A mind map drawn as a picture, not a list: the topic in the middle, each
 * branch a coloured card, its leaves inside it, deeper levels indented under
 * their leaf. One shared drawing for the visuals library and the topic
 * summary under every question («جمع‌بندی مبحث»).
 *
 * The outline is API data ({text, note?, children: [...]}, bank_visuals
 * body_format=tree): every string goes in through textContent.
 */

const HUES = 5;

function node(tag, className, text) {
    const element = document.createElement(tag);
    if (className) element.className = className;
    if (text !== undefined && text !== null && text !== '') {
        element.textContent = String(text);
        element.dir = 'auto';
    }
    return element;
}

const kids = (item) => (Array.isArray(item?.children) ? item.children : []);

function leaves(items, depth) {
    const list = node('ul', `x-mind__leaves x-mind__leaves--d${Math.min(depth, 4)}`);
    for (const item of items) {
        const li = node('li', 'x-mind__leaf');
        li.append(node('span', 'x-mind__text', item.text));
        if (item.note) li.append(node('span', 'x-mind__note', item.note));
        if (kids(item).length > 0) li.append(leaves(kids(item), depth + 1));
        list.append(li);
    }
    return list;
}

/** The whole map as one element; empty outline gives an empty element. */
export function renderMindmap(outline, { compact = false } = {}) {
    const map = node('div', `x-mind${compact ? ' x-mind--compact' : ''}`);
    if (!outline || typeof outline !== 'object') return map;

    const root = node('div', 'x-mind__root');
    root.append(node('span', 'x-mind__root-text', outline.text));
    if (outline.note) root.append(node('span', 'x-mind__root-note', outline.note));
    map.append(root);

    const branches = node('div', 'x-mind__branches');
    kids(outline).forEach((branch, index) => {
        const card = node('section', `x-mind__branch is-h${index % HUES}`);
        const head = node('div', 'x-mind__branch-head');
        head.append(node('span', 'x-mind__branch-text', branch.text));
        if (branch.note) head.append(node('span', 'x-mind__note', branch.note));
        card.append(head);
        if (kids(branch).length > 0) card.append(leaves(kids(branch), 1));
        branches.append(card);
    });
    map.append(branches);
    return map;
}
