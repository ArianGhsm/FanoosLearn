const BIDI_CONTROL = /[\u061c\u200e\u200f\u202a-\u202e\u2066-\u2069]/g;

export const RESOURCE_TYPE_FILTERS = Object.freeze([
  ['lecture_note', 'جزوه'],
  ['discipline_note', 'جزوه تخصصی'],
  ['summary', 'خلاصه'],
  ['cheat_sheet', 'خلاصه سریع'],
  ['question_bank', 'بانک سؤال'],
  ['past_exam', 'آزمون گذشته'],
  ['flashcards', 'فلش‌کارت'],
  ['audio', 'صوت'],
  ['other', 'سایر منابع'],
]);

const RESOURCE_TYPE_LABELS = new Map([
  ['booklet', 'جزوه'], ['lecture_note', 'جزوه'], ['lecture-notes', 'جزوه'], ['note', 'جزوه'],
  ['discipline_note', 'جزوه تخصصی'], ['dentnote', 'جزوه تخصصی'],
  ['summary', 'خلاصه'], ['cheat_sheet', 'خلاصه سریع'], ['cheatsheet', 'خلاصه سریع'],
  ['question_bank', 'بانک سؤال'], ['question-bank', 'بانک سؤال'], ['questions', 'بانک سؤال'],
  ['past_exam', 'آزمون گذشته'], ['past-exam', 'آزمون گذشته'], ['past_questions', 'آزمون گذشته'],
  ['flashcard', 'فلش‌کارت'], ['flashcards', 'فلش‌کارت'],
  ['audio', 'صوت'], ['podcast', 'صوت'],
  ['video', 'ویدئو'],
  ['other', 'منبع'], ['file', 'منبع'], ['document', 'منبع'],
]);

export function safeText(value, max = 5000) {
  const normalized = String(value ?? '')
    .normalize('NFKC')
    .replace(/ي/g, 'ی')
    .replace(/ك/g, 'ک')
    .replace(BIDI_CONTROL, '')
    .replace(/\s+/g, ' ')
    .trim();
  if (normalized.length <= max) return normalized;
  return `${normalized.slice(0, Math.max(0, max - 1)).trimEnd()}…`;
}

export function resourceTypeLabel(typeKey) {
  const key = safeText(typeKey, 80).toLowerCase();
  return RESOURCE_TYPE_LABELS.get(key) || 'منبع';
}

export function normalizeRows(payload) {
  if (Array.isArray(payload)) return payload;
  if (Array.isArray(payload?.items)) return payload.items;
  if (Array.isArray(payload?.rows)) return payload.rows;
  if (Array.isArray(payload?.data)) return payload.data;
  return [];
}

export function normalizeCourses(payload) {
  const rows = Array.isArray(payload?.courses) ? payload.courses : normalizeRows(payload);
  const byId = new Map();
  rows.forEach((row) => {
    const id = safeText(row?.course_id ?? row?.id, 80);
    if (!id || byId.has(id)) return;
    const title = safeText(row?.course_title ?? row?.title, 180) || 'درس';
    const code = safeText(row?.course_code ?? row?.code, 80);
    byId.set(id, { id, title, code, label: code ? `${title} · ${code}` : title });
  });
  return [...byId.values()].sort((a, b) => a.title.localeCompare(b.title, 'fa'));
}

export function normalizeResource(row, courseMap = new Map()) {
  const id = safeText(row?.id ?? row?.resource_id, 80);
  const courseId = safeText(row?.course_id, 80);
  const course = courseMap.get(courseId);
  return {
    id,
    title: safeText(row?.title, 240) || 'منبع آموزشی',
    description: safeText(row?.description, 1800),
    topic: safeText(row?.topic, 240),
    professor: safeText(row?.professor_name ?? row?.professor, 180),
    typeKey: safeText(row?.type_key ?? row?.typeKey ?? row?.type, 80).toLowerCase(),
    typeLabel: resourceTypeLabel(row?.type_key ?? row?.typeKey ?? row?.type),
    courseId,
    courseTitle: course?.title || safeText(row?.course_title ?? row?.courseTitle, 180),
    courseCode: course?.code || safeText(row?.course_code ?? row?.courseCode, 80),
    version: Number.isFinite(Number(row?.current_version_no ?? row?.version_no ?? row?.version)) ? Number(row.current_version_no ?? row.version_no ?? row.version) : null,
    updatedAt: safeText(row?.updated_at ?? row?.updatedAt, 80),
    lifecycle: safeText(row?.lifecycle_status ?? row?.lifecycle ?? row?.status, 80).toLowerCase(),
    visibility: safeText(row?.visibility, 80).toLowerCase(),
    accessLevel: safeText(row?.access_level ?? row?.accessLevel, 80).toLowerCase(),
    formatKey: safeText(row?.format_key ?? row?.formatKey, 80).toLowerCase(),
  };
}

export function catalogAccessPresentation(resource) {
  const protectedAccess = resource.accessLevel === 'entitled' || resource.visibility === 'restricted';
  if (protectedAccess) {
    return { key: 'protected', label: 'دسترسی محافظت‌شده', tone: 'warning', icon: 'lock' };
  }
  if (resource.visibility === 'private') {
    return { key: 'private', label: 'منبع خصوصی', tone: 'neutral', icon: 'lock' };
  }
  return { key: 'workspace', label: 'قابل مشاهده در فضای آموزشی', tone: 'success', icon: 'unlock' };
}

export const DELIVERY_PRESENTATION = Object.freeze({
  available: {
    title: 'آماده دریافت',
    body: 'دسترسی این منبع برای حساب فعلی تأیید شده است.',
    action: 'دریافت امن',
    tone: 'success',
  },
  preparing: {
    title: 'در حال آماده‌سازی',
    body: 'مجوز کوتاه‌عمر دریافت و دسترسی دوباره بررسی می‌شود.',
    action: 'در حال آماده‌سازی…',
    tone: 'info',
  },
  ready: {
    title: 'آماده شد',
    body: 'نسخه مجاز منبع از مسیر امن آماده شد.',
    action: 'دریافت دوباره',
    tone: 'success',
  },
  expired: {
    title: 'مجوز دریافت منقضی شد',
    body: 'برای حفظ امنیت، دریافت را دوباره از همین صفحه آغاز کنید.',
    action: 'تلاش دوباره',
    tone: 'warning',
  },
  denied: {
    title: 'دسترسی تأیید نشد',
    body: 'این منبع با دسترسی فعلی حساب قابل دریافت نیست.',
    action: null,
    tone: 'danger',
  },
  unavailable: {
    title: 'منبع فعلاً آماده نیست',
    body: 'فایل یا نسخه قابل دریافت این منبع در حال حاضر در دسترس نیست.',
    action: 'بررسی دوباره',
    tone: 'neutral',
  },
});

export function deliveryStateFromError(error) {
  const code = safeText(error?.code ?? error?.data?.code, 100).toLowerCase();
  const status = Number(error?.status ?? error?.statusCode ?? error?.response?.status ?? 0);
  if (code.includes('expired') || code === 'download_token_invalid' || code === 'delivery_token_invalid') return 'expired';
  if (status === 403 || ['resource_access_denied', 'entitlement_required', 'rbac_denied', 'resource_private'].includes(code)) return 'denied';
  if (status === 404 || ['download_unavailable', 'resource_unavailable', 'delivery_token_unavailable'].includes(code)) return 'unavailable';
  if (status === 409 || code === 'object_not_verified' || code === 'resource_version_unavailable') return 'unavailable';
  return 'unavailable';
}

export function appendHighlightedText(container, value, query) {
  const text = safeText(value);
  const needle = safeText(query, 120);
  if (!needle || needle.length < 2) {
    container.append(document.createTextNode(text));
    return;
  }
  const haystack = text.toLocaleLowerCase('fa');
  const normalizedNeedle = needle.toLocaleLowerCase('fa');
  let cursor = 0;
  let index = haystack.indexOf(normalizedNeedle);
  while (index !== -1) {
    if (index > cursor) container.append(document.createTextNode(text.slice(cursor, index)));
    const mark = document.createElement('mark');
    mark.className = 'f3-learning-highlight';
    mark.textContent = text.slice(index, index + needle.length);
    container.append(mark);
    cursor = index + needle.length;
    index = haystack.indexOf(normalizedNeedle, cursor);
  }
  if (cursor < text.length) container.append(document.createTextNode(text.slice(cursor)));
}

export function isTrustworthyCount(rows) {
  return Array.isArray(rows) && rows.length < 100;
}
