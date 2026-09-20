/*
 * In-question study tools: a note on a question, and highlights over its
 * stem. No DOM, no network -- the rules and the storage live here so they
 * can be tested directly, the same split runner-state.js and
 * runner-persistence.js already follow.
 *
 * Two decisions worth stating, because both are places the obvious version
 * is wrong:
 *
 * 1. Everything is keyed by question **id**, never by position. The legacy
 *    launcher keyed its notes and highlights by question index, which works
 *    only because its paper is in a fixed order. Ours is shuffled per
 *    attempt (ExamAttemptShuffle), so an index-keyed note would reattach
 *    itself to a different question on the student's next attempt -- and
 *    silently, since a note is plausible text next to almost any question.
 *    Keying by id also means a note written in one attempt is still there
 *    in the next, which is most of the point of writing one.
 *
 * 2. A highlight is stored as a pair of offsets, never as the highlighted
 *    text. Notes are the student's own words and are theirs to keep; the
 *    question is the product. Storing "the part the student found
 *    important" as text would reconstruct the stem in localStorage a
 *    fragment at a time, which is exactly what the per-question pacing
 *    exists to prevent. Offsets are meaningless without the question in
 *    front of them.
 *
 * Storage access is wrapped the way runner-settings.js and
 * runner-persistence.js wrap theirs: a private window throws on the access
 * itself rather than returning null, and the runner has to keep working
 * with nothing stored.
 */

const NOTES_KEY_PREFIX = 'fanoos.examNotes.v1.';
const HIGHLIGHTS_KEY_PREFIX = 'fanoos.examHighlights.v1.';

const MAX_NOTE_LENGTH = 2000;

function notesKey(assessmentId) {
    return `${NOTES_KEY_PREFIX}${assessmentId}`;
}

function highlightsKey(assessmentId) {
    return `${HIGHLIGHTS_KEY_PREFIX}${assessmentId}`;
}

function readMap(key) {
    try {
        const raw = window.localStorage.getItem(key);
        if (!raw) return {};
        const parsed = JSON.parse(raw);
        return parsed && typeof parsed === 'object' && !Array.isArray(parsed) ? parsed : {};
    } catch {
        return {};
    }
}

function writeMap(key, map) {
    try {
        window.localStorage.setItem(key, JSON.stringify(map));
        return true;
    } catch {
        return false;
    }
}

/* ------------------------------------------------------------------ notes */

/** Every note for one assessment, as `{ [questionId]: text }`. Never throws. */
export function loadNotes(assessmentId) {
    const stored = readMap(notesKey(assessmentId));
    const notes = {};
    for (const [questionId, value] of Object.entries(stored)) {
        if (typeof value === 'string' && value !== '') {
            notes[questionId] = value.slice(0, MAX_NOTE_LENGTH);
        }
    }
    return notes;
}

/**
 * Writes one question's note, or removes it when the text is blank --
 * clearing a note must not leave an empty string behind that later reads as
 * "this question has a note". Returns whether the write landed.
 */
export function saveNote(assessmentId, questionId, text) {
    const key = notesKey(assessmentId);
    const map = readMap(key);
    const trimmed = typeof text === 'string' ? text.trim() : '';
    if (trimmed === '') {
        delete map[questionId];
    } else {
        map[questionId] = trimmed.slice(0, MAX_NOTE_LENGTH);
    }
    return writeMap(key, map);
}

/* ------------------------------------------------------------- highlights */

/**
 * Normalises a set of ranges: clamps them to the text, drops empty ones,
 * and merges any that overlap or touch.
 *
 * Merging is not cosmetic. Highlighting a word, then highlighting a phrase
 * containing it, would otherwise store two ranges that render as nested
 * <mark>s -- visibly darker in the overlap, and growing every time the
 * student reselects. Merged, the same selection twice is a no-op.
 */
export function mergeRanges(ranges, textLength) {
    const limit = Number.isFinite(textLength) && textLength > 0 ? Math.floor(textLength) : 0;
    const clean = [];

    for (const range of Array.isArray(ranges) ? ranges : []) {
        if (!range || typeof range !== 'object') continue;
        const start = Math.max(0, Math.min(limit, Math.floor(Number(range.start))));
        const end = Math.max(0, Math.min(limit, Math.floor(Number(range.end))));
        if (!Number.isFinite(start) || !Number.isFinite(end) || end <= start) continue;
        clean.push({ start, end });
    }

    clean.sort((a, b) => a.start - b.start || a.end - b.end);

    const merged = [];
    for (const range of clean) {
        const last = merged[merged.length - 1];
        // `>=` rather than `>`: two ranges that merely touch (…5][5…) are one
        // continuous highlight to the eye, so they are one range in storage.
        if (last && range.start <= last.end) {
            last.end = Math.max(last.end, range.end);
            continue;
        }
        merged.push({ ...range });
    }

    return merged;
}

/** Every highlight for one assessment, as `{ [questionId]: [{start,end}] }`. Never throws. */
export function loadHighlights(assessmentId) {
    const stored = readMap(highlightsKey(assessmentId));
    const highlights = {};
    for (const [questionId, value] of Object.entries(stored)) {
        const ranges = mergeRanges(value, Number.MAX_SAFE_INTEGER);
        if (ranges.length > 0) {
            highlights[questionId] = ranges;
        }
    }
    return highlights;
}

/**
 * Adds one range to a question's highlights and stores the merged result.
 * `textLength` is the length of the stem the offsets were taken against, so
 * a stale range can never point past the end of the text.
 */
export function addHighlight(assessmentId, questionId, range, textLength) {
    const key = highlightsKey(assessmentId);
    const map = readMap(key);
    const existing = Array.isArray(map[questionId]) ? map[questionId] : [];
    const merged = mergeRanges([...existing, range], textLength);
    if (merged.length === 0) {
        delete map[questionId];
    } else {
        map[questionId] = merged;
    }
    return writeMap(key, map);
}

/** Drops every highlight on one question. */
export function clearHighlights(assessmentId, questionId) {
    const key = highlightsKey(assessmentId);
    const map = readMap(key);
    delete map[questionId];
    return writeMap(key, map);
}

/**
 * Splits `text` into the runs a renderer should draw, as
 * `[{ text, highlighted }]`. Pure: the caller builds the nodes, so nothing
 * here needs a DOM and the segmentation can be tested on its own.
 */
export function highlightSegments(text, ranges) {
    const source = String(text ?? '');
    const merged = mergeRanges(ranges, source.length);
    if (merged.length === 0) {
        return [{ text: source, highlighted: false }];
    }

    const segments = [];
    let cursor = 0;
    for (const { start, end } of merged) {
        if (start > cursor) {
            segments.push({ text: source.slice(cursor, start), highlighted: false });
        }
        segments.push({ text: source.slice(start, end), highlighted: true });
        cursor = end;
    }
    if (cursor < source.length) {
        segments.push({ text: source.slice(cursor), highlighted: false });
    }

    return segments;
}
