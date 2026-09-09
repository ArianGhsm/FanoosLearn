const DATE_KEY_RE = /^\d{4}-\d{2}-\d{2}$/;
const UTC_SQL_RE = /^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?$/;

export const SCHEDULE_MODES = Object.freeze({
  today: Object.freeze({ label: 'امروز', stepDays: 1 }),
  week: Object.freeze({ label: 'هفته', stepDays: 7 }),
  upcoming: Object.freeze({ label: 'پیشِ رو', stepDays: 30 }),
});

export const EVENT_TYPE_LABELS = Object.freeze({
  class: 'کلاس',
  exam: 'آزمون',
  deadline: 'مهلت',
  event: 'رویداد',
  other: 'سایر',
});

export const STATUS_LABELS = Object.freeze({
  scheduled: 'برنامه‌ریزی‌شده',
  completed: 'تکمیل‌شده',
  cancelled: 'لغوشده',
});

export function normalizeText(value, maxLength = 240) {
  if (value === null || value === undefined) return '';
  if (!['string', 'number'].includes(typeof value)) return '';
  const normalized = String(value)
    .normalize('NFKC')
    .replace(/\u064A/g, '\u06CC')
    .replace(/\u0643/g, '\u06A9')
    .replace(/[\u200e\u200f\u202a-\u202e\u2066-\u2069]/g, '')
    .replace(/\s+/g, ' ')
    .trim();
  if (normalized.length <= maxLength) return normalized;
  return `${Array.from(normalized).slice(0, Math.max(1, maxLength - 1)).join('')}…`;
}

export function validTimeZone(value) {
  const timeZone = normalizeText(value, 96);
  if (!timeZone) return '';
  try {
    new Intl.DateTimeFormat('en-US', { timeZone }).format(new Date(0));
    return timeZone;
  } catch (_error) {
    return '';
  }
}

export function isDateKey(value) {
  if (!DATE_KEY_RE.test(String(value || ''))) return false;
  const [year, month, day] = String(value).split('-').map(Number);
  const parsed = new Date(Date.UTC(year, month - 1, day, 12));
  return parsed.getUTCFullYear() === year
    && parsed.getUTCMonth() === month - 1
    && parsed.getUTCDate() === day;
}

export function addDays(dateKey, amount) {
  if (!isDateKey(dateKey)) return '';
  const parsed = new Date(`${dateKey}T12:00:00Z`);
  parsed.setUTCDate(parsed.getUTCDate() + Number(amount || 0));
  return parsed.toISOString().slice(0, 10);
}

export function compareDateKeys(left, right) {
  return String(left || '').localeCompare(String(right || ''), 'en');
}

export function weekStartSaturday(dateKey) {
  if (!isDateKey(dateKey)) return '';
  const parsed = new Date(`${dateKey}T12:00:00Z`);
  const sinceSaturday = (parsed.getUTCDay() + 1) % 7;
  return addDays(dateKey, -sinceSaturday);
}

export function weekKeys(anchorDateKey) {
  const start = weekStartSaturday(anchorDateKey);
  if (!start) return [];
  return Array.from({ length: 7 }, (_unused, index) => addDays(start, index));
}

export function parseBackendInstant(value) {
  const raw = normalizeText(value, 80);
  if (!raw) return null;
  let iso = raw;
  if (UTC_SQL_RE.test(raw)) iso = `${raw.replace(' ', 'T')}Z`;
  iso = iso.replace(/(\.\d{3})\d+(?=Z|[+-]\d{2}:?\d{2}|$)/i, '$1');
  const parsed = new Date(iso);
  return Number.isFinite(parsed.getTime()) ? parsed : null;
}

function datePartsForInstant(date, timeZone) {
  try {
    const parts = new Intl.DateTimeFormat('en-CA', {
      timeZone,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).formatToParts(date);
    const pick = (type) => parts.find((part) => part.type === type)?.value || '';
    const key = `${pick('year')}-${pick('month')}-${pick('day')}`;
    return isDateKey(key) ? key : '';
  } catch (_error) {
    return '';
  }
}

export function workspaceTodayKey(timeZone, now = new Date()) {
  if (!validTimeZone(timeZone)) return '';
  const instant = now instanceof Date ? now : new Date(now);
  if (!Number.isFinite(instant.getTime())) return '';
  return datePartsForInstant(instant, timeZone);
}

export function instantDateKey(value, timeZone) {
  const instant = parseBackendInstant(value);
  return instant && validTimeZone(timeZone) ? datePartsForInstant(instant, timeZone) : '';
}

export function formatDateKey(dateKey, options = {}) {
  if (!isDateKey(dateKey)) return '—';
  const date = new Date(`${dateKey}T12:00:00Z`);
  const config = {
    calendar: 'persian',
    weekday: options.weekday === false ? undefined : 'long',
    day: 'numeric',
    month: options.month || 'long',
    year: options.year ? 'numeric' : undefined,
    timeZone: 'UTC',
  };
  Object.keys(config).forEach((key) => config[key] === undefined && delete config[key]);
  try {
    return new Intl.DateTimeFormat('fa-IR-u-ca-persian', config).format(date);
  } catch (_error) {
    return dateKey;
  }
}

export function formatTime(value, timeZone) {
  const instant = parseBackendInstant(value);
  if (!instant || !validTimeZone(timeZone)) return '—';
  try {
    return new Intl.DateTimeFormat('fa-IR', {
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone,
    }).format(instant);
  } catch (_error) {
    return '—';
  }
}

export function formatDateTime(value, timeZone) {
  const instant = parseBackendInstant(value);
  if (!instant || !validTimeZone(timeZone)) return '—';
  try {
    return new Intl.DateTimeFormat('fa-IR-u-ca-persian', {
      weekday: 'long',
      day: 'numeric',
      month: 'long',
      hour: '2-digit',
      minute: '2-digit',
      hour12: false,
      timeZone,
    }).format(instant);
  } catch (_error) {
    return '—';
  }
}

export function buildRange(mode, anchorDateKey, todayDateKey) {
  const safeMode = Object.hasOwn(SCHEDULE_MODES, mode) ? mode : 'today';
  const today = isDateKey(todayDateKey) ? todayDateKey : '';
  let anchor = isDateKey(anchorDateKey) ? anchorDateKey : today;
  if (!anchor) return null;

  if (safeMode === 'today') return { from: anchor, to: anchor, anchor };
  if (safeMode === 'week') {
    const from = weekStartSaturday(anchor);
    return { from, to: addDays(from, 6), anchor };
  }

  if (today && compareDateKeys(anchor, today) < 0) anchor = today;
  return { from: anchor, to: addDays(anchor, 89), anchor };
}

function canonicalEventType(value) {
  const key = normalizeText(value, 32).toLowerCase();
  return Object.hasOwn(EVENT_TYPE_LABELS, key) ? key : '';
}

function canonicalStatus(value) {
  const key = normalizeText(value, 32).toLowerCase();
  return Object.hasOwn(STATUS_LABELS, key) ? key : '';
}

function eventIdentity(row, index) {
  const opaque = normalizeText(row?.id, 80);
  return opaque || `schedule-row-${index}`;
}

export function normalizeRows(input, timeZone) {
  const rows = Array.isArray(input) ? input : [];
  const normalized = [];
  let skipped = 0;

  rows.forEach((row, index) => {
    if (!row || typeof row !== 'object') {
      skipped += 1;
      return;
    }
    const startsAt = normalizeText(row.starts_at, 80);
    const startInstant = parseBackendInstant(startsAt);
    const dateKey = startInstant ? instantDateKey(startsAt, timeZone) : '';
    if (!startInstant || !dateKey) {
      skipped += 1;
      return;
    }

    const endsAt = normalizeText(row.ends_at, 80);
    const endInstant = endsAt ? parseBackendInstant(endsAt) : null;
    const courseTitle = normalizeText(row.course_title, 160);
    const title = normalizeText(row.title, 180) || courseTitle || 'رویداد آموزشی';
    const courseCode = normalizeText(row.course_code, 64);
    const eventType = canonicalEventType(row.event_type);
    const status = canonicalStatus(row.status);

    normalized.push(Object.freeze({
      key: eventIdentity(row, index),
      title,
      courseTitle,
      courseCode,
      courseId: normalizeText(row.course_id, 80),
      offeringId: normalizeText(row.offering_id, 80),
      startsAt,
      endsAt: endInstant ? endsAt : '',
      startMs: startInstant.getTime(),
      endMs: endInstant ? endInstant.getTime() : null,
      dateKey,
      location: typeof row.location_text === 'string' ? normalizeText(row.location_text, 180) : '',
      eventType,
      eventTypeLabel: EVENT_TYPE_LABELS[eventType] || '',
      status,
      statusLabel: STATUS_LABELS[status] || '',
      instructor: typeof row.instructor_name === 'string'
        ? normalizeText(row.instructor_name, 140)
        : (typeof row.professor_name === 'string' ? normalizeText(row.professor_name, 140) : ''),
      notes: typeof row.notes === 'string'
        ? normalizeText(row.notes, 600)
        : (typeof row.note === 'string' ? normalizeText(row.note, 600) : ''),
    }));
  });

  normalized.sort((left, right) => (left.startMs - right.startMs) || left.title.localeCompare(right.title, 'fa'));
  return { rows: normalized, skipped };
}

export function groupByDate(rows) {
  const groups = new Map();
  (Array.isArray(rows) ? rows : []).forEach((row) => {
    if (!groups.has(row.dateKey)) groups.set(row.dateKey, []);
    groups.get(row.dateKey).push(row);
  });
  return [...groups.entries()].sort(([left], [right]) => compareDateKeys(left, right));
}

export function deriveCourseOptions(rows) {
  const courses = new Map();
  (Array.isArray(rows) ? rows : []).forEach((row) => {
    if (!row.courseCode) return;
    const key = row.courseCode.toLocaleLowerCase('en-US');
    if (!courses.has(key)) courses.set(key, { value: row.courseCode, label: row.courseTitle || row.courseCode });
  });
  return [...courses.values()].sort((left, right) => left.label.localeCompare(right.label, 'fa'));
}

export function deriveEventTypeOptions(rows) {
  const keys = new Set((Array.isArray(rows) ? rows : []).map((row) => row.eventType).filter(Boolean));
  return Object.entries(EVENT_TYPE_LABELS)
    .filter(([key]) => keys.has(key))
    .map(([value, label]) => ({ value, label }));
}

export function filterRows(rows, filters = {}) {
  const course = normalizeText(filters.course, 64).toLocaleLowerCase('en-US');
  const type = canonicalEventType(filters.type);
  return (Array.isArray(rows) ? rows : []).filter((row) => {
    if (course && row.courseCode.toLocaleLowerCase('en-US') !== course) return false;
    if (type && row.eventType !== type) return false;
    return true;
  });
}

export function temporalCues(rows, range, todayDateKey, now = new Date()) {
  const cues = new Map();
  if (!range || !isDateKey(todayDateKey)) return cues;
  if (compareDateKeys(range.from, todayDateKey) > 0 || compareDateKeys(range.to, todayDateKey) < 0) return cues;
  const nowMs = now instanceof Date ? now.getTime() : new Date(now).getTime();
  if (!Number.isFinite(nowMs)) return cues;

  const ordered = (Array.isArray(rows) ? rows : []).filter((row) => Number.isFinite(row.startMs));
  const current = ordered.find((row) => Number.isFinite(row.endMs) && row.startMs <= nowMs && row.endMs > nowMs);
  if (current) cues.set(current.key, 'current');

  const next = ordered.find((row) => row.startMs > nowMs);
  if (next && next.key !== current?.key) cues.set(next.key, 'next');
  return cues;
}

export function rangeLabel(mode, range) {
  if (!range) return '';
  if (mode === 'today') return formatDateKey(range.from, { year: true });
  if (mode === 'week') {
    return `${formatDateKey(range.from, { weekday: false })} تا ${formatDateKey(range.to, { weekday: false, year: true })}`;
  }
  return `از ${formatDateKey(range.from, { weekday: false })} تا ${formatDateKey(range.to, { weekday: false, year: true })}`;
}
