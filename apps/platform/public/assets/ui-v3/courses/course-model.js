const BIDI_CONTROL = /[\u202A-\u202E\u2066-\u2069]/g;

const STATUS_LABELS = Object.freeze({
  planned: 'برنامه‌ریزی‌شده',
  open: 'باز',
  active: 'فعال',
  closed: 'پایان‌یافته',
  archived: 'بایگانی‌شده',
  scheduled: 'زمان‌بندی‌شده',
  completed: 'برگزارش‌شده',
  cancelled: 'لغوشده',
  published: 'منتشرشده',
  in_progress: 'در حال انجام',
});

export function cleanText(value) {
  return String(value == null ? '' : value)
    .normalize('NFKC')
    .replace(BIDI_CONTROL, '')
    .replace(/\s+/g, ' ')
    .trim();
}

function rows(value) {
  return Array.isArray(value) ? value : [];
}

function optionalNumber(value) {
  if (value == null || value === '') return null;
  const number = Number(value);
  return Number.isFinite(number) ? number : null;
}

function termRouteKey(term) {
  return cleanText(term?.key || term?.name);
}

function canonicalDateValue(value) {
  const raw = cleanText(value);
  if (!raw) return NaN;
  const normalized = /^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}(?:\.\d+)?$/.test(raw)
    ? `${raw.replace(' ', 'T')}Z`
    : raw;
  return Date.parse(normalized);
}

export function localizeStatus(value) {
  const key = cleanText(value).toLowerCase();
  return STATUS_LABELS[key] || '';
}

export function buildCourseCatalog(projection = {}) {
  const terms = rows(projection.terms).map((row) => ({
    id: row.id || null,
    key: cleanText(row.term_key),
    name: cleanText(row.name),
    startsOn: cleanText(row.starts_on),
    endsOn: cleanText(row.ends_on),
    status: cleanText(row.status),
  }));
  const termById = new Map(terms.filter((term) => term.id).map((term) => [String(term.id), term]));
  const grouped = new Map();

  rows(projection.courses).forEach((row) => {
    const id = row.id ? String(row.id) : '';
    const code = cleanText(row.course_code);
    const key = id || code;
    if (!key || !code) return;

    if (!grouped.has(key)) {
      grouped.set(key, {
        id: id || null,
        code,
        routeKey: code,
        title: cleanText(row.title) || 'درس',
        creditValue: optionalNumber(row.credit_value),
        offerings: [],
        offeringIds: new Set(),
        terms: [],
        sessions: [],
        instructors: [],
      });
    }

    const course = grouped.get(key);
    if (!course.title && row.title) course.title = cleanText(row.title);
    if (course.creditValue == null) course.creditValue = optionalNumber(row.credit_value);

    const offeringId = row.offering_id ? String(row.offering_id) : '';
    let offering = offeringId ? course.offerings.find((item) => item.id === offeringId) : null;
    if (offeringId && !offering) {
      const projectedTerm = row.term_id ? termById.get(String(row.term_id)) : null;
      const term = projectedTerm || (row.term_name ? {
        id: row.term_id || null,
        key: '',
        name: cleanText(row.term_name),
        startsOn: '',
        endsOn: '',
        status: '',
      } : null);
      offering = {
        id: offeringId,
        sectionKey: cleanText(row.section_key),
        status: cleanText(row.offering_status),
        term,
        sessions: [],
      };
      course.offerings.push(offering);
      course.offeringIds.add(offeringId);
    }

    if (offering?.term) {
      const identity = offering.term.id ? String(offering.term.id) : termRouteKey(offering.term);
      if (identity && !course.terms.some((term) => (term.id ? String(term.id) : termRouteKey(term)) === identity)) {
        course.terms.push(offering.term);
      }
    }

    if (row.session_id || row.session_title || row.sequence_no != null) {
      const sessionKey = row.session_id
        ? String(row.session_id)
        : `${offeringId}:${String(row.sequence_no ?? '')}:${cleanText(row.starts_at)}:${cleanText(row.session_title)}`;
      if (!course.sessions.some((session) => session.key === sessionKey)) {
        const session = {
          key: sessionKey,
          id: row.session_id || null,
          offeringId: offeringId || null,
          sequence: optionalNumber(row.sequence_no),
          title: cleanText(row.session_title) || (row.sequence_no != null ? `جلسه ${row.sequence_no}` : 'جلسه'),
          startsAt: cleanText(row.starts_at),
          endsAt: cleanText(row.ends_at),
          status: cleanText(row.session_status),
          sectionKey: offering?.sectionKey || cleanText(row.section_key),
          term: offering?.term || null,
        };
        course.sessions.push(session);
        if (offering) offering.sessions.push(session);
      }
    }

    const optionalInstructor = cleanText(row.instructor_name || row.instructor_display_name);
    if (optionalInstructor && !course.instructors.includes(optionalInstructor)) {
      course.instructors.push(optionalInstructor);
    }
  });

  const courses = [...grouped.values()];
  courses.forEach((course) => {
    course.sessions.sort((a, b) => {
      const dateA = canonicalDateValue(a.startsAt);
      const dateB = canonicalDateValue(b.startsAt);
      if (Number.isFinite(dateA) && Number.isFinite(dateB) && dateA !== dateB) return dateA - dateB;
      if (a.sequence != null && b.sequence != null && a.sequence !== b.sequence) return a.sequence - b.sequence;
      return a.title.localeCompare(b.title, 'fa');
    });
    course.offerings.sort((a, b) => {
      const aStart = a.term?.startsOn || '';
      const bStart = b.term?.startsOn || '';
      if (aStart !== bStart) return bStart.localeCompare(aStart);
      return a.sectionKey.localeCompare(b.sectionKey, 'fa');
    });
    course.terms.sort((a, b) => (b.startsOn || '').localeCompare(a.startsOn || ''));
  });
  courses.sort((a, b) => a.title.localeCompare(b.title, 'fa'));

  return {
    directory: projection.directory || null,
    terms,
    courses,
  };
}

export function findCourseByCode(courses, courseCode) {
  const target = cleanText(courseCode).toLocaleLowerCase('en-US');
  if (!target) return null;
  return rows(courses).find((course) => course.code.toLocaleLowerCase('en-US') === target) || null;
}

export function routeTermKey(term) {
  return termRouteKey(term);
}

export function courseMatchesTerm(course, selectedTerm) {
  const target = cleanText(selectedTerm).toLocaleLowerCase('en-US');
  if (!target) return true;
  return course.terms.some((term) => termRouteKey(term).toLocaleLowerCase('en-US') === target);
}

export function filterCourses(courses, query, selectedTerm) {
  const needle = cleanText(query).toLocaleLowerCase('fa');
  return rows(courses).filter((course) => {
    if (!courseMatchesTerm(course, selectedTerm)) return false;
    if (!needle) return true;
    const haystack = [
      course.title,
      course.code,
      ...course.terms.map((term) => term.name),
      ...course.offerings.map((offering) => offering.sectionKey),
    ].join(' ').toLocaleLowerCase('fa');
    return haystack.includes(needle);
  });
}

export function sessionsForTerm(course, selectedTerm) {
  if (!selectedTerm) return course.sessions;
  const target = cleanText(selectedTerm).toLocaleLowerCase('en-US');
  return course.sessions.filter((session) => termRouteKey(session.term).toLocaleLowerCase('en-US') === target);
}

export function findNearestSession(course, now = Date.now()) {
  return course.sessions.find((session) => {
    const status = cleanText(session.status).toLowerCase();
    if (status === 'cancelled' || status === 'archived') return false;
    const startsAt = canonicalDateValue(session.startsAt);
    return Number.isFinite(startsAt) && startsAt >= now;
  }) || null;
}

export function latestResource(resources) {
  return [...rows(resources)].sort((a, b) => {
    const aTime = canonicalDateValue(a.updated_at);
    const bTime = canonicalDateValue(b.updated_at);
    if (Number.isFinite(aTime) && Number.isFinite(bTime)) return bTime - aTime;
    return 0;
  })[0] || null;
}

export function latestGrade(grades, course) {
  const scoped = rows(grades).filter((row) => rowMatchesCourse(row, course));
  return scoped.sort((a, b) => {
    const aTime = canonicalDateValue(a.updated_at);
    const bTime = canonicalDateValue(b.updated_at);
    if (Number.isFinite(aTime) && Number.isFinite(bTime)) return bTime - aTime;
    return 0;
  })[0] || null;
}

export function rowMatchesCourse(row, course) {
  if (!row || !course) return false;
  if (row.course_id && course.id && String(row.course_id) === String(course.id)) return true;
  if (row.course_code && cleanText(row.course_code).toLocaleLowerCase('en-US') === course.code.toLocaleLowerCase('en-US')) return true;
  if (row.offering_id && course.offeringIds.has(String(row.offering_id))) return true;
  return false;
}

export function scopedRows(sourceRows, course) {
  return rows(sourceRows).filter((row) => rowMatchesCourse(row, course));
}

export function firstAssessment(assessments) {
  return rows(assessments)[0] || null;
}

export function hasContentPart(part) {
  return part?.state === 'ready' && rows(part.data).length > 0;
}
