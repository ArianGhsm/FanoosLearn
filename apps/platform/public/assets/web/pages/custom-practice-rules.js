/*
 * The builder's counting rules, without the DOM, so they can be tested.
 */

/**
 * How many questions a choice can produce: the chosen topics' counts for the
 * chosen source, or the courses' whole count when no topic is chosen.
 */
export function availableCount(options, chosenTopics, source) {
    const key = source === 'all' ? 'total' : source;
    if (!options) return 0;
    if (!chosenTopics || chosenTopics.length === 0) return Number(options[key] ?? 0);
    return (options.topics ?? [])
        .filter((topic) => chosenTopics.includes(topic.topic))
        .reduce((sum, topic) => sum + Number(topic[key] ?? 0), 0);
}

/** A timed exam's default length: a minute and a half a question, rounded to five. */
export function suggestedMinutes(questionCount) {
    return Math.max(5, Math.round((Number(questionCount) * 1.5) / 5) * 5);
}
