/*
 * Pure rules of the bank pages, kept apart so they can be tested without a
 * browser (tests/web/bank_rules_test.mjs).
 */

/** Persian and Arabic letter forms, digits and ZWNJ folded so a search matches however it was typed. */
export function fold(text) {
    return String(text ?? '')
        .replace(/[يى]/g, 'ی')
        .replace(/ك/g, 'ک')
        .replace(/[۰-۹]/g, (d) => String('۰۱۲۳۴۵۶۷۸۹'.indexOf(d)))
        .replace(/[٠-٩]/g, (d) => String('٠١٢٣٤٥٦٧٨٩'.indexOf(d)))
        .replace(/‌/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
        .toLowerCase();
}

/** Does any of these texts contain the query (folded)? An empty query matches everything. */
export function matches(query, ...texts) {
    const q = fold(query);
    if (q === '') return true;
    return texts.some((text) => fold(text).includes(q));
}

/** "۱۳۹۷ تا ۱۴۰۴", "۱۴۰۴", or null when the subject has no questions yet. */
export function yearSpan(first, last, faDigits) {
    if (first === null || first === undefined) return null;
    return first === last ? faDigits(first) : `${faDigits(first)} تا ${faDigits(last)}`;
}

/** Share of a topic in its subject, 0–100, for the bar behind the row. */
export function share(part, whole) {
    if (!whole) return 0;
    return Math.max(2, Math.round((part * 100) / whole));
}

/** A sitting's label: "دستیاری ۱۴۰۴" or "دستیاری ۱۴۰۴ · نوبت ۲". */
export function sittingLabel(sitting, faDigits) {
    return `${sitting.type} ${faDigits(sitting.year)}${sitting.round > 1 ? ` · نوبت ${faDigits(sitting.round)}` : ''}`;
}
