/*
 * Pure rules of the saved page (tests/web/saved_rules_test.mjs).
 */

/**
 * Saved questions grouped by topic, biggest group first; a question with no
 * topic goes in the last group (topic null). Order inside a group is kept.
 */
export function groupByTopic(items) {
    const groups = new Map();
    for (const item of items) {
        const topic = typeof item.topic === 'string' && item.topic.trim() !== '' ? item.topic : null;
        if (!groups.has(topic)) groups.set(topic, { topic, items: [] });
        groups.get(topic).items.push(item);
    }
    return [...groups.values()].sort((a, b) => {
        if ((a.topic === null) !== (b.topic === null)) return a.topic === null ? 1 : -1;
        return b.items.length - a.items.length;
    });
}

/**
 * Highlights as flashcards: one card per highlighted phrase, the question it
 * came from (its opening words) on the back.
 */
export function highlightCards(items) {
    const cards = [];
    for (const item of items) {
        for (const fragment of Array.isArray(item.fragments) ? item.fragments : []) {
            if (typeof fragment !== 'string' || fragment.trim() === '') continue;
            cards.push({ front: fragment.trim(), back: item.preview, topic: item.topic ?? null, source: item.assessment_title ?? null });
        }
    }
    return cards;
}
