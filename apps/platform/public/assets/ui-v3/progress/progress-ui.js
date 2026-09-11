const FALLBACK_NUMBER = new Intl.NumberFormat('fa-IR');
const FALLBACK_DATE = new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'short', day: 'numeric' });
const FALLBACK_DATE_TIME = new Intl.DateTimeFormat('fa-IR', { year: 'numeric', month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });

const KIND_LABELS = Object.freeze({
  practice: 'تمرین',
  quiz: 'کوییز',
  mock_exam: 'آزمون آزمایشی',
  past_exam: 'آزمون گذشته',
  exam: 'آزمون',
});

function node(tag, options = {}, ...children) {
  const element = document.createElement(tag);
  if (options.className) element.className = options.className;
  if (options.text !== undefined && options.text !== null) element.textContent = String(options.text);
  for (const [key, value] of Object.entries(options.attrs || {})) {
    if (value === undefined || value === null || value === false) continue;
    if (value === true) element.setAttribute(key, '');
    else element.setAttribute(key, String(value));
  }
  for (const [eventName, handler] of Object.entries(options.on || {})) {
    if (typeof handler === 'function') element.addEventListener(eventName, handler);
  }
  children.flat(Infinity).forEach((child) => {
    if (child === undefined || child === null || child === false) return;
    element.append(child instanceof Node ? child : document.createTextNode(String(child)));
  });
  return element;
}

function button(label, { variant = 'secondary', onClick, type = 'button', attrs = {} } = {}) {
  return node('button', {
    className: `f3-progress-button f3-progress-button--${variant}`,
    text: label,
    attrs: { type, ...attrs },
    on: { click: onClick },
  });
}

function formatNumber(ctx, value) {
  if (value === null || value === undefined || value === '') return '—';
  if (ctx?.format?.number) return ctx.format.number(value);
  const numeric = Number(value);
  return Number.isFinite(numeric) ? FALLBACK_NUMBER.format(numeric) : String(value);
}

function formatDate(ctx, value, withTime = false) {
  if (!value) return '';
  if (withTime && ctx?.format?.dateTime) return ctx.format.dateTime(value);
  if (!withTime && ctx?.format?.date) return ctx.format.date(value);
  const parsed = new Date(value);
  if (Number.isNaN(parsed.getTime())) return '';
  return (withTime ? FALLBACK_DATE_TIME : FALLBACK_DATE).format(parsed);
}

function safeText(value, fallback = '') {
  if (value === null || value === undefined) return fallback;
  return String(value).normalize('NFKC').replace(/[\u202A-\u202E\u2066-\u2069]/g, '').trim() || fallback;
}

function assessmentKind(row) {
  return KIND_LABELS[String(row?.assessment_kind || row?.type || '').toLowerCase()] || 'آزمون';
}

function courseLabel(course) {
  if (!course) return '';
  return safeText(course.title || course.course_title, '');
}

function pageHeader(title, description, eyebrow = '') {
  return node('header', { className: 'f3-progress-page-header' },
    eyebrow ? node('div', { className: 'f3-progress-eyebrow', text: eyebrow }) : null,
    node('h1', { text: title }),
    node('p', { text: description }),
  );
}

function statePanel(kind, title, body, action) {
  const panel = node('section', {
    className: `f3-progress-state f3-progress-state--${kind}`,
    attrs: { role: kind === 'error' || kind === 'permission' ? 'alert' : 'status' },
  },
    node('div', { className: 'f3-progress-state__mark', attrs: { 'aria-hidden': 'true' } }),
    node('div', { className: 'f3-progress-state__copy' },
      node('h2', { text: title }),
      node('p', { text: body }),
    ),
  );
  if (action?.label && typeof action?.onClick === 'function') {
    panel.append(button(action.label, { variant: 'secondary', onClick: action.onClick }));
  }
  return panel;
}

export function renderLoading(root, mode = 'assessments') {
  root.replaceChildren(
    pageHeader(mode === 'grades' ? 'نمرات' : 'آزمون‌ها و بانک سؤال', mode === 'grades' ? 'در حال دریافت نمره‌های منتشرشده…' : 'در حال دریافت آزمون‌ها و منابع مرتبط…'),
    node('div', { className: 'f3-progress-skeletons', attrs: { 'aria-hidden': 'true' } },
      ...Array.from({ length: 3 }, () => node('div', { className: 'f3-progress-skeleton' },
        node('span', { className: 'f3-progress-skeleton__short' }),
        node('span', { className: 'f3-progress-skeleton__long' }),
        node('span', { className: 'f3-progress-skeleton__medium' }),
      )),
    ),
  );
  root.setAttribute('aria-busy', 'true');
}

export function renderFailure(root, kind, { retry, area = 'assessments' } = {}) {
  const isGrades = area === 'grades';
  const copy = {
    permission: ['دسترسی به این بخش مجاز نیست', 'برای مشاهده این اطلاعات، حساب شما باید مجوز مربوط به همین فضای آموزشی را داشته باشد.'],
    session: ['نشست شما پایان یافته است', 'برای ادامه، دوباره وارد حساب شوید.'],
    error: [isGrades ? 'نمرات دریافت نشدند' : 'اطلاعات آزمون‌ها دریافت نشد', 'ارتباط با سرویس کامل نشد. می‌توانید دوباره تلاش کنید.'],
  }[kind] || ['اطلاعات در دسترس نیست', 'در حال حاضر امکان نمایش این بخش وجود ندارد.'];
  root.replaceChildren(pageHeader(isGrades ? 'نمرات' : 'آزمون‌ها و بانک سؤال', isGrades ? 'نمره‌های رسمی منتشرشده در فضای آموزشی فعال.' : 'تمرین‌ها، آزمون‌ها و بانک سؤال در فضای آموزشی فعال.'), statePanel(kind, copy[0], copy[1], retry ? { label: 'تلاش دوباره', onClick: retry } : null));
  root.setAttribute('aria-busy', 'false');
}

function filterButton(label, active, onClick) {
  return node('button', {
    className: 'f3-progress-segment',
    text: label,
    attrs: { type: 'button', 'aria-pressed': active ? 'true' : 'false' },
    on: { click: onClick },
  });
}

function assessmentMeta(ctx, row, course) {
  const meta = node('div', { className: 'f3-progress-meta' });
  if (courseLabel(course)) meta.append(node('span', { text: courseLabel(course) }));
  if (row?.max_attempts !== null && row?.max_attempts !== undefined) meta.append(node('span', { text: `حداکثر ${formatNumber(ctx, row.max_attempts)} تلاش` }));
  const deadline = row?.deadline_at || row?.closes_at || row?.ends_at;
  const deadlineText = formatDate(ctx, deadline, true);
  if (deadlineText) meta.append(node('span', { text: `مهلت: ${deadlineText}` }));
  return meta;
}

function assessmentStatusLabel(row) {
  const lifecycle = String(row?.presentation_state || row?.attempt_state || '').toLowerCase();
  if (lifecycle === 'upcoming') return 'به‌زودی';
  if (lifecycle === 'completed' || lifecycle === 'scored') return 'تکمیل‌شده';
  if (lifecycle === 'in_progress') return 'در حال انجام';
  if (row?.requires_entitlement) return 'نیازمند دسترسی معتبر';
  return 'منتشرشده';
}

function assessmentCard(ctx, item, actions) {
  const { row, course } = item;
  const article = node('article', { className: 'f3-progress-assessment-card' },
    node('div', { className: 'f3-progress-assessment-card__body' },
      node('div', { className: 'f3-progress-assessment-card__topline' },
        node('span', { className: 'f3-progress-type', text: assessmentKind(row) }),
        node('span', { className: 'f3-progress-status', text: assessmentStatusLabel(row) }),
      ),
      node('h3', { text: safeText(row?.title, 'آزمون') }),
      assessmentMeta(ctx, row, course),
    ),
    node('div', { className: 'f3-progress-assessment-card__action' },
      button(row?.active_attempt_id || row?.presentation_state === 'in_progress' ? 'ادامه' : 'مشاهده', { variant: 'quiet', onClick: () => actions.openAssessment(row) }),
    ),
  );
  return article;
}

function sectionHeading(title, description = '', trailing = null) {
  return node('div', { className: 'f3-progress-section-heading' },
    node('div', {}, node('h2', { text: title }), description ? node('p', { text: description }) : null),
    trailing,
  );
}

function resourceShelf(ctx, rows, courses, actions, config) {
  const section = node('section', { className: 'f3-progress-section', attrs: { 'aria-labelledby': config.headingId } },
    sectionHeading(config.title, config.description, config.openLibrary ? button(config.libraryLabel, { variant: 'quiet', onClick: config.openLibrary }) : null),
  );
  section.querySelector('h2')?.setAttribute('id', config.headingId);
  if (!Array.isArray(rows)) {
    section.append(statePanel('partial', config.failureTitle, config.failureBody, config.retry ? { label: 'تلاش دوباره', onClick: config.retry } : null));
    return section;
  }
  if (!rows.length) {
    section.append(node('div', { className: 'f3-progress-inline-empty' }, node('p', { text: config.emptyText })));
    return section;
  }
  const list = node('div', { className: 'f3-progress-question-bank-list' });
  rows.slice(0, 4).forEach((row) => {
    const course = courses.find((item) => String(item.id) === String(row.course_id));
    list.append(node('article', { className: 'f3-progress-question-bank-row' },
      node('div', {},
        node('span', { className: 'f3-progress-type', text: config.typeLabel }),
        node('h3', { text: safeText(row.title, config.typeLabel) }),
        courseLabel(course) ? node('p', { text: courseLabel(course) }) : null,
        row.topic ? node('p', { className: 'f3-progress-resource-topic', text: `موضوع: ${safeText(row.topic)}` }) : null,
      ),
      config.openResource ? button('باز کردن در منابع', { variant: 'quiet', onClick: () => config.openResource(row, course) }) : null,
    ));
  });
  section.append(list);
  return section;
}

function questionBankShelf(ctx, rows, courses, actions) {
  return resourceShelf(ctx, rows, courses, actions, {
    title: 'بانک سؤال', description: 'منابعی که در کتابخانه با نوع «بانک سؤال» منتشر شده‌اند.', headingId: 'progress-question-bank-heading',
    typeLabel: 'بانک سؤال', libraryLabel: 'همه بانک سؤال‌ها', openLibrary: actions.openQuestionBankLibrary,
    openResource: actions.openQuestionBankResource, retry: actions.retryQuestionBanks,
    failureTitle: 'بانک سؤال دریافت نشد', failureBody: 'آزمون‌ها همچنان در دسترس‌اند؛ بخش منابع را می‌توانید جداگانه بررسی کنید.',
    emptyText: 'بانک سؤال منتشرشده‌ای با این فیلتر پیدا نشد.',
  });
}

function pastExamShelf(ctx, rows, courses, actions) {
  return resourceShelf(ctx, rows, courses, actions, {
    title: 'آزمون‌های گذشته', description: 'آزمون‌های گذشته به‌صورت منبع ساختاریافته و متصل به درس.', headingId: 'progress-past-exam-heading',
    typeLabel: 'آزمون گذشته', libraryLabel: 'همه آزمون‌های گذشته', openLibrary: actions.openPastExamLibrary,
    openResource: actions.openPastExamResource, retry: actions.retryPastExams,
    failureTitle: 'آزمون‌های گذشته دریافت نشد', failureBody: 'فهرست آزمون‌ها برقرار است؛ منابع آزمون گذشته موقتاً در دسترس نیستند.',
    emptyText: 'آزمون گذشتهٔ منتشرشده‌ای با این فیلتر پیدا نشد.',
  });
}

export function renderAssessmentLanding(root, model, actions) {
  const { ctx, assessments, questionBanks, pastExams, courses, filters } = model;
  const kindOptions = [
    ['', 'همه'],
    ['practice', 'تمرین'],
    ['quiz', 'کوییز'],
    ['mock_exam', 'آزمون آزمایشی'],
    ['past_exam', 'آزمون گذشته'],
  ];
  const header = pageHeader('آزمون‌ها و بانک سؤال', 'تمرین‌ها، آزمون‌های منتشرشده و منابع بانک سؤال را در یک مسیر پیدا کنید.', 'پیشرفت تحصیلی');
  const controls = node('section', { className: 'f3-progress-controls', attrs: { 'aria-label': 'فیلتر آزمون‌ها' } },
    node('div', { className: 'f3-progress-segments', attrs: { role: 'group', 'aria-label': 'نوع آزمون' } },
      ...kindOptions.map(([key, label]) => filterButton(label, filters.kind === key, () => actions.setKind(key))),
    ),
  );
  const searchLabel = node('label', { className: 'f3-progress-resource-search' },
    node('span', { text: 'جست‌وجوی بانک سؤال و آزمون گذشته' }),
    node('input', { attrs: { type: 'search', value: filters.resourceQuery || '', placeholder: 'عنوان، موضوع یا توضیحات…', 'aria-label': 'جست‌وجوی منابع ارزیابی' }, on: { change: (event) => actions.setResourceQuery?.(event.target.value) } }),
  );
  controls.append(searchLabel);

  if (courses.length) {
    const label = node('label', { className: 'f3-progress-course-filter' }, node('span', { text: 'درس' }));
    const select = node('select', { attrs: { 'aria-label': 'فیلتر بر اساس درس' }, on: { change: (event) => actions.setCourse(event.currentTarget.value) } },
      node('option', { text: 'همه درس‌ها', attrs: { value: '', selected: filters.courseKey === '' } }),
      ...courses.map((course) => node('option', { text: course.title, attrs: { value: course.presentationKey, selected: filters.courseKey === course.presentationKey } })),
    );
    label.append(select);
    controls.append(label);
  }

  const availableSection = node('section', { className: 'f3-progress-section', attrs: { 'aria-labelledby': 'progress-assessment-heading' } },
    sectionHeading('آزمون‌های منتشرشده', 'فقط مواردی نمایش داده می‌شوند که backend برای حساب شما منتشر کرده است.'),
  );
  availableSection.querySelector('h2')?.setAttribute('id', 'progress-assessment-heading');
  if (!assessments.length) {
    availableSection.append(node('div', { className: 'f3-progress-inline-empty' }, node('h3', { text: 'آزمونی با این فیلتر پیدا نشد' }), node('p', { text: 'نوع آزمون یا درس را تغییر دهید. اگر موردی منتشر شود، در همین بخش ظاهر خواهد شد.' })));
  } else {
    availableSection.append(node('div', { className: 'f3-progress-assessment-list' }, ...assessments.map((item) => assessmentCard(ctx, item, actions))));
  }

  const lifecycleNotice = node('aside', { className: 'f3-progress-authority-note' },
    node('strong', { text: 'وضعیت زمانی و سابقه تلاش' }),
    node('p', { text: 'مهلت، تعداد تلاش مصرف‌شده یا نتیجه‌های قبلی فقط وقتی نمایش داده می‌شوند که projection رسمی آن‌ها را برگرداند؛ این صفحه چیزی را حدس نمی‌زند.' }),
  );

  root.replaceChildren(header, controls, availableSection, questionBankShelf(ctx, questionBanks, courses, actions), pastExamShelf(ctx, pastExams, courses, actions), lifecycleNotice);
  root.setAttribute('aria-busy', 'false');
}

export function renderAssessmentDetail(root, model, actions) {
  const { ctx, row, course } = model;
  const main = node('article', { className: 'f3-progress-detail' },
    button('بازگشت به آزمون‌ها', { variant: 'quiet', onClick: actions.back }),
    node('div', { className: 'f3-progress-detail__headline' },
      node('span', { className: 'f3-progress-type', text: assessmentKind(row) }),
      node('h1', { text: safeText(row?.title, 'آزمون') }),
      assessmentMeta(ctx, row, course),
    ),
    row?.instructions ? node('section', { className: 'f3-progress-detail__instructions' }, node('h2', { text: 'راهنما' }), node('p', { text: safeText(row.instructions) })) : null,
    node('section', { className: 'f3-progress-detail__facts' },
      node('div', {}, node('span', { text: 'وضعیت' }), node('strong', { text: assessmentStatusLabel(row) })),
      row?.max_attempts !== null && row?.max_attempts !== undefined ? node('div', {}, node('span', { text: 'سقف تلاش' }), node('strong', { text: `${formatNumber(ctx, row.max_attempts)} بار` })) : null,
      row?.active_attempt_id ? node('div', {}, node('span', { text: 'تلاش ذخیره‌شده' }), node('strong', { text: 'قابل ادامه' })) : null,
      row?.source_resource_id ? node('div', {}, node('span', { text: 'منبع سؤال' }), node('strong', { text: 'منبع ساختاریافتهٔ متصل' })) : null,
      row?.requires_entitlement ? node('div', {}, node('span', { text: 'دسترسی' }), node('strong', { text: 'نیازمند دسترسی معتبر' })) : node('div', {}, node('span', { text: 'دسترسی' }), node('strong', { text: 'طبق مجوز حساب' })),
    ),
    node('div', { className: 'f3-progress-authority-note' },
      node('strong', { text: 'امتیازدهی روی سرور انجام می‌شود' }),
      node('p', { text: 'پاسخ صحیح پیش از ثبت نهایی به مرورگر ارسال نمی‌شود. ذخیره، ثبت نهایی و نتیجه فقط از مسیر canonical آزمون انجام می‌شود.' }),
    ),
    node('div', { className: 'f3-progress-detail__actions' },
      button(row?.active_attempt_id ? 'ادامه تلاش' : 'شروع تلاش', { variant: 'primary', onClick: actions.start, attrs: actions.startDisabled ? { disabled: true, 'aria-disabled': 'true' } : {} }),
    ),
  );
  root.replaceChildren(main);
  root.setAttribute('aria-busy', 'false');
}

function answerLabel(question, index) {
  const choices = Array.isArray(question?.choices) ? question.choices : [];
  return choices[index] !== undefined ? safeText(choices[index]) : '';
}

function submitDialog(ctx, attempt, answers, onConfirm) {
  const questions = Array.isArray(attempt?.questions) ? attempt.questions : [];
  const answered = questions.filter((question) => Object.prototype.hasOwnProperty.call(answers, String(question.id))).length;
  const dialog = node('dialog', { className: 'f3-progress-submit-dialog', attrs: { 'aria-labelledby': 'progress-submit-title', 'aria-describedby': 'progress-submit-copy' } },
    node('div', { className: 'f3-progress-submit-dialog__body' },
      node('h2', { text: 'ثبت نهایی آزمون', attrs: { id: 'progress-submit-title' } }),
      node('p', { text: `از ${formatNumber(ctx, questions.length)} سؤال، به ${formatNumber(ctx, answered)} سؤال پاسخ داده‌اید. پس از ثبت نهایی، این تلاش برای امتیازدهی به سرور ارسال می‌شود.`, attrs: { id: 'progress-submit-copy' } }),
      answered < questions.length ? node('p', { className: 'f3-progress-submit-dialog__warning', text: 'بعضی سؤال‌ها بدون پاسخ هستند.' }) : null,
      node('div', { className: 'f3-progress-submit-dialog__actions' },
        button('بازگشت و بررسی', { variant: 'secondary', onClick: () => dialog.close?.() || dialog.removeAttribute('open') }),
        button('ثبت نهایی', { variant: 'danger', onClick: () => { dialog.close?.(); onConfirm(); } }),
      ),
    ),
  );
  return dialog;
}

export function renderAssessmentAttempt(root, model, actions) {
  const { ctx, assessment, attempt, answers, activeIndex = 0, mutation = {} } = model;
  const questions = Array.isArray(attempt?.questions) ? attempt.questions : [];
  if (!questions.length) {
    root.replaceChildren(pageHeader(safeText(assessment?.title, 'آزمون'), 'تلاش روی سرور ایجاد شد.'), statePanel('error', 'سؤالی برای نمایش دریافت نشد', 'این تلاش را ثبت نکنید. از فهرست آزمون‌ها بازگردید و دوباره تلاش کنید.', { label: 'بازگشت', onClick: actions.back }));
    root.setAttribute('aria-busy', 'false');
    return;
  }
  const index = Math.max(0, Math.min(activeIndex, questions.length - 1));
  const question = questions[index];
  const questionId = String(question.id);
  const selected = answers[questionId];
  const answeredCount = questions.filter((item) => Object.prototype.hasOwnProperty.call(answers, String(item.id))).length;

  const nav = node('nav', { className: 'f3-progress-question-nav', attrs: { 'aria-label': 'پیمایش سؤال‌ها' } },
    node('div', { className: 'f3-progress-question-nav__summary' },
      node('strong', { text: `${formatNumber(ctx, answeredCount)} از ${formatNumber(ctx, questions.length)} پاسخ داده‌شده` }),
      node('span', { text: `نسخه ذخیره ${formatNumber(ctx, attempt?.revision || 1)}` }),
    ),
    node('div', { className: 'f3-progress-question-nav__items' },
      ...questions.map((item, itemIndex) => {
        const answered = Object.prototype.hasOwnProperty.call(answers, String(item.id));
        return node('button', {
          className: `f3-progress-question-nav__item${answered ? ' is-answered' : ''}${itemIndex === index ? ' is-current' : ''}`,
          text: formatNumber(ctx, itemIndex + 1),
          attrs: { type: 'button', 'aria-current': itemIndex === index ? 'step' : null, 'aria-label': `سؤال ${formatNumber(ctx, itemIndex + 1)}${answered ? '، پاسخ داده شده' : ''}` },
          on: { click: () => actions.goToQuestion(itemIndex) },
        });
      }),
    ),
  );

  const fieldset = node('fieldset', { className: 'f3-progress-question' },
    node('legend', {},
      node('span', { className: 'f3-progress-question__number', text: `سؤال ${formatNumber(ctx, index + 1)} از ${formatNumber(ctx, questions.length)}` }),
      node('span', { className: 'f3-progress-question__prompt', text: safeText(question.prompt, 'سؤال') }),
    ),
  );
  (Array.isArray(question.choices) ? question.choices : []).forEach((choice, choiceIndex) => {
    const id = `f3-progress-choice-${index}-${choiceIndex}`;
    const input = node('input', {
      attrs: { id, type: 'radio', name: `question-${index}`, value: String(choiceIndex), checked: Number(selected) === choiceIndex },
      on: { change: () => actions.answer(questionId, choiceIndex) },
    });
    fieldset.append(node('label', { className: `f3-progress-choice${Number(selected) === choiceIndex ? ' is-selected' : ''}`, attrs: { for: id } }, input, node('span', { className: 'f3-progress-choice__marker', attrs: { 'aria-hidden': 'true' } }), node('span', { text: safeText(choice) })));
  });

  const saveMessage = mutation.saveState === 'saving' ? 'در حال ذخیره پاسخ‌ها…' : mutation.saveState === 'saved' ? 'پاسخ‌ها روی سرور ذخیره شدند.' : mutation.saveState === 'conflict' ? 'نسخه تلاش تغییر کرده است؛ ادامه این تلاش نیاز به بازیابی canonical دارد.' : mutation.saveState === 'error' ? 'ذخیره پاسخ‌ها انجام نشد.' : 'ذخیره موقت، revision همین تلاش را روی سرور به‌روزرسانی می‌کند.';
  const controls = node('div', { className: 'f3-progress-attempt-actions' },
    node('div', { className: 'f3-progress-attempt-actions__paging' },
      button('سؤال قبلی', { variant: 'secondary', onClick: () => actions.goToQuestion(index - 1), attrs: index === 0 ? { disabled: true } : {} }),
      button('سؤال بعدی', { variant: 'secondary', onClick: () => actions.goToQuestion(index + 1), attrs: index === questions.length - 1 ? { disabled: true } : {} }),
    ),
    node('div', { className: 'f3-progress-attempt-actions__commit' },
      node('p', { className: `f3-progress-save-state f3-progress-save-state--${mutation.saveState || 'idle'}`, text: saveMessage, attrs: { 'aria-live': 'polite' } }),
      button('ذخیره موقت', { variant: 'secondary', onClick: actions.save, attrs: mutation.pending || mutation.saveState === 'conflict' ? { disabled: true } : {} }),
      button('ثبت نهایی پاسخ‌ها', { variant: 'primary', onClick: actions.requestSubmit, attrs: mutation.pending || mutation.saveState === 'conflict' ? { disabled: true } : {} }),
    ),
  );

  const layout = node('div', { className: 'f3-progress-attempt-layout' }, nav, node('main', { className: 'f3-progress-attempt-main' },
    node('header', { className: 'f3-progress-attempt-header' },
      button('خروج از تلاش', { variant: 'quiet', onClick: actions.back }),
      node('div', {}, node('span', { className: 'f3-progress-type', text: assessmentKind(assessment) }), node('h1', { text: safeText(attempt?.title || assessment?.title, 'آزمون') })),
    ),
    fieldset,
    controls,
  ));
  root.replaceChildren(layout);
  root.setAttribute('aria-busy', mutation.pending ? 'true' : 'false');

  actions.bindSubmitDialog?.(() => {
    const dialog = submitDialog(ctx, attempt, answers, actions.submit);
    root.append(dialog);
    if (typeof dialog.showModal === 'function') dialog.showModal();
    else dialog.setAttribute('open', '');
  });
}

export function renderAssessmentResult(root, model, actions) {
  const { ctx, assessment, attempt, result } = model;
  const basisPoints = Number(result?.score_basis_points);
  const score = Number.isFinite(basisPoints) ? `${formatNumber(ctx, basisPoints / 100)}٪` : 'نتیجه ثبت شده';
  const header = node('header', { className: 'f3-progress-result-header' },
    node('span', { className: 'f3-progress-type', text: 'نتیجه آزمون' }),
    node('h1', { text: safeText(assessment?.title || attempt?.title, 'آزمون') }),
    node('div', { className: 'f3-progress-result-score' }, node('span', { text: 'امتیاز' }), node('strong', { text: score })),
    result?.correct_count !== undefined && result?.question_count !== undefined ? node('p', { text: `${formatNumber(ctx, result.correct_count)} پاسخ صحیح از ${formatNumber(ctx, result.question_count)}` }) : null,
  );
  const questions = new Map((Array.isArray(attempt?.questions) ? attempt.questions : []).map((question) => [String(question.id), question]));
  const review = Array.isArray(result?.review) ? result.review : [];
  const reviewSection = node('section', { className: 'f3-progress-review' }, sectionHeading('مرور پاسخ‌ها', 'پاسخ صحیح فقط از نتیجه canonical پس از ثبت نهایی نمایش داده می‌شود.'));
  if (!review.length) {
    reviewSection.append(node('div', { className: 'f3-progress-inline-empty' }, node('p', { text: 'جزئیات مرور برای این نتیجه در projection فعلی موجود نیست.' })));
  } else {
    review.forEach((row, index) => {
      const question = questions.get(String(row.id)) || {};
      const selectedText = Number.isInteger(Number(row.selected)) ? answerLabel(question, Number(row.selected)) : '';
      const correctText = Number.isInteger(Number(row.correct)) ? answerLabel(question, Number(row.correct)) : '';
      reviewSection.append(node('article', { className: `f3-progress-review-row ${row.is_correct ? 'is-correct' : 'is-incorrect'}` },
        node('div', { className: 'f3-progress-review-row__heading' }, node('span', { text: formatNumber(ctx, index + 1) }), node('h3', { text: safeText(question.prompt, `سؤال ${formatNumber(ctx, index + 1)}`) }), node('span', { className: 'f3-progress-status', text: row.is_correct ? 'صحیح' : 'نیاز به مرور' })),
        selectedText ? node('p', {}, node('strong', { text: 'پاسخ شما: ' }), document.createTextNode(selectedText)) : node('p', { text: 'پاسخی ثبت نشده بود.' }),
        correctText ? node('p', {}, node('strong', { text: 'پاسخ صحیح: ' }), document.createTextNode(correctText)) : null,
        row.explanation ? node('p', { className: 'f3-progress-review-row__explanation', text: safeText(row.explanation) }) : null,
      ));
    });
  }
  root.replaceChildren(node('div', { className: 'f3-progress-result' }, button('بازگشت به آزمون‌ها', { variant: 'quiet', onClick: actions.back }), header, reviewSection, node('div', { className: 'f3-progress-authority-note' }, node('strong', { text: 'نتیجه از سرور دریافت شده است' }), node('p', { text: 'امتیاز در مرورگر محاسبه نشده و این صفحه فقط نتیجه canonical را ارائه می‌کند.' }))));
  root.setAttribute('aria-busy', 'false');
}

function gradeValue(ctx, row) {
  if (row?.score === null || row?.score === undefined || row?.score === '') return { score: 'ثبت نشده', max: row?.max_score !== null && row?.max_score !== undefined ? formatNumber(ctx, row.max_score) : '' };
  return { score: formatNumber(ctx, row.score), max: row?.max_score !== null && row?.max_score !== undefined ? formatNumber(ctx, row.max_score) : '' };
}

function gradeRowsTable(ctx, rows) {
  const table = node('table', { className: 'f3-progress-grade-table' },
    node('thead', {}, node('tr', {}, node('th', { text: 'ارزیابی', attrs: { scope: 'col' } }), node('th', { text: 'نمره', attrs: { scope: 'col' } }), node('th', { text: 'از', attrs: { scope: 'col' } }), node('th', { text: 'آخرین ثبت', attrs: { scope: 'col' } }))),
  );
  const body = node('tbody');
  rows.forEach((row) => {
    const value = gradeValue(ctx, row);
    const date = formatDate(ctx, row.updated_at, false) || '—';
    body.append(node('tr', {},
      node('th', { attrs: { scope: 'row', 'data-label': 'ارزیابی' } }, node('span', { text: safeText(row.item_title || row.gradebook_title, 'نمره') }), row.gradebook_title && row.item_title && row.gradebook_title !== row.item_title ? node('small', { text: safeText(row.gradebook_title) }) : null),
      node('td', { attrs: { 'data-label': 'نمره' } }, node('strong', { className: 'f3-progress-grade-score', text: value.score })),
      node('td', { text: value.max || '—', attrs: { 'data-label': 'از' } }),
      node('td', { text: date, attrs: { 'data-label': 'آخرین ثبت' } }),
    ));
  });
  table.append(body);
  return table;
}

function gradeGroups(ctx, rows, { courseScope = '' } = {}) {
  const termGroups = new Map();
  rows.forEach((row) => {
    const explicitTerm = safeText(row.term_name || row.term_title || row.term_key, '');
    const termKey = explicitTerm || '__no_term_projection__';
    if (!termGroups.has(termKey)) termGroups.set(termKey, []);
    termGroups.get(termKey).push(row);
  });
  const root = node('div', { className: 'f3-progress-grade-groups' });
  termGroups.forEach((termRows, termKey) => {
    const term = node('section', { className: 'f3-progress-grade-term' });
    if (termKey !== '__no_term_projection__') term.append(sectionHeading(termKey, 'نمره‌های منتشرشده این ترم'));
    const courses = new Map();
    termRows.forEach((row) => {
      const code = safeText(row.course_code, '');
      const title = safeText(row.course_title, 'سایر نمرات');
      const key = `${code}\u0000${title}`;
      if (!courses.has(key)) courses.set(key, { code, title, rows: [] });
      courses.get(key).rows.push(row);
    });
    courses.forEach((course) => {
      if (courseScope && course.code !== courseScope) return;
      const heading = node('div', { className: 'f3-progress-grade-course__heading' },
        node('div', {}, node('h3', { text: course.title }), course.code ? node('bdi', { className: 'f3-progress-course-code', text: course.code, attrs: { dir: 'ltr' } }) : null),
        node('span', { text: `${formatNumber(ctx, course.rows.length)} مورد منتشرشده` }),
      );
      term.append(node('section', { className: 'f3-progress-grade-course' }, heading, gradeRowsTable(ctx, course.rows)));
    });
    if (term.childElementCount) root.append(term);
  });
  return root;
}

export function renderGrades(root, model, actions = {}) {
  const { ctx, rows, courseScope = '', courseTitle = '' } = model;
  const title = courseScope ? (courseTitle || 'نمرات درس') : 'نمرات';
  const description = courseScope ? 'نمره‌های رسمی منتشرشده برای این درس.' : 'فقط نمره‌هایی که به‌صورت رسمی برای حساب شما منتشر شده‌اند.';
  const header = pageHeader(title, description, courseScope ? 'درس' : 'پیشرفت تحصیلی');
  if (!rows.length) {
    root.replaceChildren(header, statePanel('empty', courseScope ? 'برای این درس نمره‌ای منتشر نشده است' : 'هنوز نمره‌ای منتشر نشده است', 'وقتی نتیجه‌ای به‌صورت رسمی منتشر شود، در این بخش نمایش داده می‌شود.'));
    root.setAttribute('aria-busy', 'false');
    return;
  }
  const summary = node('aside', { className: 'f3-progress-grade-summary' },
    node('div', {}, node('strong', { text: `${formatNumber(ctx, rows.length)} نمره منتشرشده` }), node('span', { text: 'نمایش مستقیم داده‌های رسمی' })),
    node('p', { text: 'میانگین، معدل یا نمره نهایی محاسبه نمی‌شود؛ projection فعلی سیاست وزن‌دهی و کامل‌بودن همه اجزا را اعلام نمی‌کند.' }),
  );
  root.replaceChildren(header, summary, gradeGroups(ctx, rows, { courseScope }));
  root.setAttribute('aria-busy', 'false');
}

export function renderPartialNotice(root, title, body, retry) {
  root.append(statePanel('partial', title, body, retry ? { label: 'تلاش دوباره', onClick: retry } : null));
}
