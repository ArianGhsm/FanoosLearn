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

/*
 * منابع آزمون: what changed in a year's list against the year before it.
 * `years` is the API's list, newest first; the year before is the next older
 * one of the same exam type (a year with no list is skipped, not invented).
 */
export function previousYear(years, index) {
    const year = years[index];
    if (!year) return null;
    return years.slice(index + 1).find((other) => other.type === year.type && other.year < year.year) ?? null;
}

const referenceKey = (ref) => String(ref.edition_ref ?? '').split('@')[0];

/**
 * 'new' (a book the subject did not have), 'edition' (the same book, another
 * edition), 'scope' (the same edition, other announced chapters), or null
 * (unchanged, or no earlier year to compare with).
 */
export function referenceChange(ref, previousSubject) {
    if (!previousSubject) return null;
    const same = previousSubject.references.find((other) => other.edition_ref === ref.edition_ref);
    if (same) return sameScope(same, ref) ? null : 'scope';
    return previousSubject.references.some((other) => referenceKey(other) === referenceKey(ref)) ? 'edition' : 'new';
}

/*
 * Two years name the same scope when they name the same chapters (and the
 * same parts of them); when neither names chapters, when their words agree.
 * A year that names chapters against one that only describes them in words
 * cannot be compared, so it is not called a change.
 */
function sameScope(before, now) {
    const named = (ref) => (Array.isArray(ref.chapters) && ref.chapters.some((chapter) => chapter.in_scope === true)
        ? ref.chapters.filter((chapter) => chapter.in_scope === true).map((chapter) => `${chapter.number}:${chapter.partial ?? ''}`).join(',')
        : null);
    const a = named(before);
    const b = named(now);
    if (a !== null && b !== null) return a === b;
    if (a !== null || b !== null) return true;
    return fold(before.scope) === fold(now.scope);
}

/** The books of the year before that this year's list no longer names (in any edition). */
export function droppedReferences(subject, previousSubject) {
    if (!previousSubject) return [];
    const kept = new Set(subject.references.map(referenceKey));
    return previousSubject.references.filter((ref) => !kept.has(referenceKey(ref)));
}

/** A book's chapters against the year's announcement: in scope is null when it names no chapters. */
export function chapterCoverage(chapters) {
    const list = Array.isArray(chapters) ? chapters : [];
    const scoped = list.some((chapter) => chapter.in_scope === true);
    return { total: list.length, inScope: scoped ? list.filter((chapter) => chapter.in_scope === true).length : null };
}

/*
 * An announcement's scope as it reads in a right-to-left line: Persian
 * digits, and a space after each comma between numbers, so "4,5,13-30"
 * neither runs together into one left-to-right number nor wraps mid-list.
 */
export function scopeText(scope, faDigits) {
    return faDigits(String(scope ?? '').replace(/(\d)\s*[,،]\s*(?=\d)/g, '$1، '));
}

/*
 * «آزمون من»: the exam a student is preparing for -- an exam type and, for a
 * specialty exam, optionally one specialty. Empty means every exam. It only
 * narrows what a page shows first; every page can still show the rest.
 */
export const ALL_EXAMS = Object.freeze({ type: '', specialty: '' });

/** A stored goal, kept only if its type still exists. */
export function normalizeGoal(goal, typeKeys) {
    const type = typeof goal?.type === 'string' ? goal.type : '';
    if (type === '' || !typeKeys.includes(type)) return { ...ALL_EXAMS };
    return { type, specialty: typeof goal?.specialty === 'string' ? goal.specialty : '' };
}

/** "همه‌ی آزمون‌ها", "دستیاری", "بورد · اندودانتیکس". */
export function goalLabel(goal, types, specialtyName) {
    const type = types.find((t) => t.key === goal.type);
    if (!type) return 'همه‌ی آزمون‌ها';
    return goal.specialty && specialtyName ? `${type.name} · ${specialtyName}` : type.name;
}

/**
 * Published papers grouped as a student looks for them: by exam and year,
 * newest first, each group's specialty papers inside it. A goal with a
 * specialty keeps only that specialty's papers of its own exam type.
 */
export function groupSittings(sittings, goal = ALL_EXAMS) {
    const groups = [];
    const byKey = new Map();
    for (const sitting of sittings) {
        if (goal.type && sitting.type_key !== goal.type) continue;
        if (goal.specialty && sitting.type_key === goal.type && sitting.subject_key && sitting.subject_key !== goal.specialty) continue;
        const key = `${sitting.type_key}:${sitting.year}`;
        if (!byKey.has(key)) {
            const group = { type: sitting.type, type_key: sitting.type_key, year: sitting.year, sittings: [] };
            byKey.set(key, group);
            groups.push(group);
        }
        byKey.get(key).sittings.push(sitting);
    }
    return groups.sort((a, b) => b.year - a.year);
}

/** For each exam type whose papers are one specialty each, its specialties by name. */
export function specialtiesByType(sittings) {
    const out = {};
    for (const sitting of sittings) {
        if (!sitting.subject_key) continue;
        const list = (out[sitting.type_key] ??= []);
        if (!list.some((s) => s.key === sitting.subject_key)) list.push({ key: sitting.subject_key, name: sitting.subject_name });
    }
    for (const list of Object.values(out)) list.sort((a, b) => a.name.localeCompare(b.name, 'fa'));
    return out;
}

/** A list with the goal's specialty first, the rest in their order. */
export function specialtyFirst(rows, specialty, keyOf = (row) => row.key) {
    if (!specialty) return rows;
    return [...rows.filter((row) => keyOf(row) === specialty), ...rows.filter((row) => keyOf(row) !== specialty)];
}
