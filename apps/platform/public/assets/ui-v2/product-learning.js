(function (global) {
  'use strict';
  const UI = global.FanoosProductUI;
  if (!UI || !UI._internal) return;
  const { text, bounded, el, button, clear, faNumber, faDate, faDateTime, faTime, money, status, resourceType, assessmentType, resultRows, skeleton, stateBlock, partialState, heading, routeFromHash, routeHash, courseGroups, findCourse, courseLabelForId, localDateKey, todayKey, addNeutralDays, weekBounds, rowMatchesCourse, pageMeta, resourceTypes } = UI._internal;

  function renderResources(container, model) {
      if (!model.resources?.ok) return stateBlock(container, 'منابع دریافت نشدند', 'کتابخانه این فضای آموزشی فعلاً در دسترس نیست.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      const courses = courseGroups(model.academics?.ok ? (model.academics.data?.courses || []) : []);
      const bar = el('form', { className: 'filter-bar', attrs: { role: 'search' }, on: { submit: (event) => { event.preventDefault(); const data = new FormData(event.currentTarget); const query = {}; ['q', 'type', 'course'].forEach((key) => { const value = text(data.get(key)); if (value) query[key] = value; }); model.handlers.navigate('resources', query); } } });
      const searchField = el('div', { className: 'filter-field filter-field--search' }, el('label', { attrs: { for: 'resource-search' }, text: 'جست‌وجو' }), el('input', { attrs: { id: 'resource-search', name: 'q', type: 'search', value: model.route.query.q || '', placeholder: 'عنوان یا موضوع منبع' } }));
      const typeField = el('div', { className: 'filter-field' }, el('label', { attrs: { for: 'resource-type' }, text: 'نوع منبع' }));
      const typeSelect = el('select', { attrs: { id: 'resource-type', name: 'type' } }, el('option', { text: 'همه نوع‌ها', attrs: { value: '' } }));
      resourceTypes.forEach((key) => typeSelect.append(el('option', { text: resourceType(key), attrs: { value: key, selected: model.route.query.type === key || null } })));
      typeField.append(typeSelect);
      const courseField = el('div', { className: 'filter-field' }, el('label', { attrs: { for: 'resource-course' }, text: 'درس' }));
      const courseSelect = el('select', { attrs: { id: 'resource-course', name: 'course' } }, el('option', { text: 'همه درس‌ها', attrs: { value: '' } }));
      courses.forEach((course) => courseSelect.append(el('option', { text: course.title, attrs: { value: course.code, selected: model.route.query.course === course.code || null } })));
      courseField.append(courseSelect);
      bar.append(searchField, typeField, courseField, button('اعمال فیلتر', { variant: 'secondary', type: 'submit' }));
      container.append(bar);
      renderResourceCards(container, resultRows(model.resources), courses, model);
    }

  function renderResourceCards(container, rows, courses, model) {
      if (!rows.length) return container.append(el('div', { className: 'state-block' }, el('div', { className: 'state-block__inner' }, el('h3', { text: 'منبعی با این فیلتر پیدا نشد' }), el('p', { text: 'فیلترها را تغییر دهید یا بعداً دوباره بررسی کنید.' }))));
      const grid = el('div', { className: 'resource-grid' });
      rows.forEach((row) => {
        const courseTitle = courseLabelForId(courses, row.course_id);
        const card = el('article', { className: 'resource-card-v2' },
          el('span', { className: 'resource-card-v2__type', text: resourceType(row.type_key || row.type) }),
          el('h3', { text: text(row.title) || 'منبع آموزشی' })
        );
        if (row.description || row.topic) card.append(el('p', { text: bounded(row.description || row.topic, 150) }));
        const meta = el('div', { className: 'resource-card-v2__meta' });
        if (courseTitle) meta.append(el('span', { text: courseTitle }));
        if (row.professor_name) meta.append(el('span', { text: text(row.professor_name) }));
        if (row.current_version_no) meta.append(el('span', { text: `نسخه ${faNumber(row.current_version_no)}` }));
        card.append(meta);
        const access = String(row.access_level || row.visibility || row.status || '').toLowerCase();
        const protectedState = access === 'protected' || row.requires_entitlement === true;
        const footer = el('div', { className: 'card-footer' }, el('span', { className: 'card-footer__state', text: protectedState ? 'دسترسی محافظت‌شده' : status(row.status || row.lifecycle_status || 'published') }));
        if (model.handlers.openResource && row.id) footer.append(button('جزئیات', { variant: 'quiet', onClick: () => model.handlers.openResource(row) }));
        card.append(footer); grid.append(card);
      });
      container.append(grid);
    }

  function renderAssessments(container, model) {
      if (!model.assessments?.ok) return stateBlock(container, 'آزمون‌ها دریافت نشدند', 'فهرست تمرین و آزمون فعلاً در دسترس نیست.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      const courses = courseGroups(model.academics?.ok ? (model.academics.data?.courses || []) : []);
      const kinds = [['', 'همه'], ['practice', 'تمرین'], ['mock_exam', 'آزمون آزمایشی'], ['past_exam', 'آزمون گذشته']];
      const segmented = el('div', { className: 'segmented-control', attrs: { role: 'group', 'aria-label': 'نوع آزمون' } });
      kinds.forEach(([key, label]) => segmented.append(el('button', { text: label, attrs: { type: 'button', 'aria-pressed': (model.route.query.kind || '') === key ? 'true' : 'false' }, on: { click: () => model.handlers.navigate('assessments', key ? { kind: key } : {}) } })));
      container.append(el('div', { className: 'product-section' }, segmented));
      renderAssessmentCards(container, resultRows(model.assessments), courses, model);
    }

  function renderAssessmentCards(container, rows, courses, model) {
      if (!rows.length) return container.append(el('div', { className: 'state-block' }, el('div', { className: 'state-block__inner' }, el('h3', { text: 'آزمونی برای نمایش وجود ندارد' }), el('p', { text: 'با انتشار آزمون یا تمرین جدید، این بخش به‌روزرسانی می‌شود.' }))));
      const grid = el('div', { className: 'resource-grid' });
      rows.forEach((row) => {
        const kind = row.assessment_kind || row.type_key || row.type;
        const courseTitle = courseLabelForId(courses, row.course_id);
        const card = el('article', { className: 'assessment-card-v2' },
          el('span', { className: 'assessment-card-v2__type', text: assessmentType(kind) }),
          el('h3', { text: text(row.title) || 'آزمون' })
        );
        const meta = el('div', { className: 'assessment-card-v2__meta' });
        if (courseTitle) meta.append(el('span', { text: courseTitle }));
        if (row.max_attempts != null) meta.append(el('span', { text: `حداکثر ${faNumber(row.max_attempts)} تلاش` }));
        if (row.current_version_no) meta.append(el('span', { text: `نسخه ${faNumber(row.current_version_no)}` }));
        card.append(meta);
        const footer = el('div', { className: 'card-footer' }, el('span', { className: 'card-footer__state', text: row.requires_entitlement ? 'نیازمند دسترسی' : 'قابل دسترسی با مجوز حساب' }));
        if (model.handlers.openAssessment && row.id) footer.append(button('مشاهده', { variant: 'quiet', onClick: () => model.handlers.openAssessment(row) }));
        card.append(footer); grid.append(card);
      });
      container.append(grid);
    }

  function renderGrades(container, model) {
      if (!model.grades?.ok) return stateBlock(container, 'نمرات دریافت نشدند', 'نمره‌های منتشرشده حساب فعلاً در دسترس نیست.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      renderGradeRows(container, resultRows(model.grades), model.context);
    }

  function renderGradeRows(container, rows, context) {
      if (!rows.length) return container.append(el('div', { className: 'state-block' }, el('div', { className: 'state-block__inner' }, el('h3', { text: 'هنوز نمره‌ای منتشر نشده است' }), el('p', { text: 'فقط نمره‌هایی که به‌صورت رسمی منتشر شده‌اند اینجا نمایش داده می‌شوند.' }))));
      const groups = new Map();
      rows.forEach((row) => { const key = text(row.course_title) || 'سایر'; if (!groups.has(key)) groups.set(key, []); groups.get(key).push(row); });
      const root = el('div', { className: 'grade-groups' });
      groups.forEach((items, courseTitle) => {
        const section = el('section', { className: 'grade-group' }, el('div', { className: 'grade-group__heading' }, el('h3', { text: courseTitle }), el('span', { text: `${faNumber(items.length)} مورد منتشرشده` })));
        const table = el('table', { className: 'grade-table' });
        const body = el('tbody');
        items.forEach((row) => {
          body.append(el('tr', {},
            el('th', { attrs: { scope: 'row' }, text: text(row.item_title || row.gradebook_title) || 'نمره' }),
            el('td', {}, el('span', { className: 'grade-score-v2', text: row.score != null ? `${faNumber(row.score)}${row.max_score != null ? ` / ${faNumber(row.max_score)}` : ''}` : '—' }), row.updated_at ? el('span', { className: 'grade-updated', text: ` · ${faDate(row.updated_at, context)}` }) : null)
          ));
        });
        table.append(body); section.append(table); root.append(section);
      });
      container.append(root);
    }

  function renderResourceDetail(container, detail, model) {
      clear(container);
      if (!detail?.ok) return stateBlock(container, 'جزئیات منبع دریافت نشد', 'اطلاعات این منبع در دسترس نیست.', { actionLabel: 'بازگشت', onAction: model.handlers.closeResource });
      const row = detail.data || {};
      const panel = el('article', { className: 'detail-panel surface-card' },
        el('button', { className: 'text-link detail-panel__back', text: '‹ منابع', attrs: { type: 'button' }, on: { click: model.handlers.closeResource } }),
        el('span', { className: 'resource-card-v2__type', text: resourceType(row.type_key || row.type) }),
        el('h3', { text: text(row.title) || 'منبع آموزشی' })
      );
      if (row.description) panel.append(el('div', { className: 'detail-panel__body', text: text(row.description) }));
      const metadata = el('div', { className: 'metadata-row' });
      if (row.current_version_no) metadata.append(el('span', { text: `نسخه ${faNumber(row.current_version_no)}` }));
      if (row.topic) metadata.append(el('span', { text: text(row.topic) }));
      if (row.professor_name) metadata.append(el('span', { text: text(row.professor_name) }));
      panel.append(metadata);
      if (model.handlers.requestDelivery && row.id) panel.append(button('دریافت امن', { variant: 'primary', onClick: () => model.handlers.requestDelivery(row) }));
      container.append(panel);
    }

  function renderAssessmentDetail(container, row, model) {
      clear(container);
      const panel = el('article', { className: 'detail-panel surface-card' },
        el('button', { className: 'text-link detail-panel__back', text: '‹ آزمون‌ها', attrs: { type: 'button' }, on: { click: model.handlers.closeAssessment } }),
        el('span', { className: 'assessment-card-v2__type', text: assessmentType(row.assessment_kind || row.type) }),
        el('h3', { text: text(row.title) || 'آزمون' }),
        el('div', { className: 'metadata-row' },
          row.max_attempts != null ? el('span', { text: `حداکثر ${faNumber(row.max_attempts)} تلاش` }) : null,
          row.requires_entitlement ? el('span', { text: 'نیازمند دسترسی معتبر' }) : null
        )
      );
      panel.append(el('div', { className: 'ui-state ui-state-info', text: 'پاسخ صحیح و امتیازدهی در مرورگر نگهداری نمی‌شود. شروع، ذخیره و ارسال تلاش فقط از endpointهای canonical آزمون انجام می‌شود.' }));
      if (model.handlers.startAssessment && row.id) panel.append(button('شروع تلاش', { variant: 'primary', onClick: () => model.handlers.startAssessment(row) }));
      container.append(panel);
    }

  function assessmentAnswers(form, questions) {
      const answers = {};
      (Array.isArray(questions) ? questions : []).forEach((question) => {
        const id = text(question?.id);
        if (!id) return;
        const selected = form.querySelector(`input[name="q-${CSS.escape(id)}"]:checked`);
        if (selected) answers[id] = Number(selected.value);
      });
      return answers;
    }

  function renderAssessmentAttempt(container, assessment, attempt, model) {
      clear(container);
      const questions = Array.isArray(attempt?.questions) ? attempt.questions : [];
      const panel = el('article', { className: 'detail-panel surface-card' },
        el('button', { className: 'text-link detail-panel__back', text: '‹ آزمون‌ها', attrs: { type: 'button' }, on: { click: model.handlers.closeAssessment } }),
        el('span', { className: 'assessment-card-v2__type', text: assessmentType(assessment?.assessment_kind || assessment?.type) }),
        el('h3', { text: text(attempt?.title || assessment?.title) || 'آزمون' }),
        el('div', { className: 'metadata-row' },
          el('span', { text: `${faNumber(questions.length)} سؤال` }),
          el('span', { text: `نسخه تلاش ${faNumber(attempt?.revision || 1)}` })
        )
      );
      if (!questions.length) {
        panel.append(el('div', { className: 'ui-state ui-state-warning', text: 'سؤال قابل‌نمایشی برای این تلاش از سرور دریافت نشد.' }));
        container.append(panel);
        return;
      }
      const form = el('form', { className: 'assessment-attempt', on: { submit: (event) => { event.preventDefault(); model.handlers.submitAssessment(assessmentAnswers(event.currentTarget, questions), event.currentTarget); } } });
      questions.forEach((question, questionIndex) => {
        const fieldset = el('fieldset', { className: 'assessment-question' },
          el('legend', { text: `${faNumber(questionIndex + 1)}. ${text(question.prompt) || 'سؤال'}` })
        );
        (Array.isArray(question.choices) ? question.choices : []).forEach((choice, choiceIndex) => {
          const inputId = `assessment-${questionIndex}-${choiceIndex}`;
          const input = el('input', { attrs: { id: inputId, type: 'radio', name: `q-${text(question.id)}`, value: String(choiceIndex) } });
          fieldset.append(el('label', { className: 'assessment-choice', attrs: { for: inputId } }, input, el('span', { text: text(choice) })));
        });
        form.append(fieldset);
      });
      const actions = el('div', { className: 'detail-actions' });
      if (model.handlers.saveAssessment) actions.append(button('ذخیره موقت', { variant: 'secondary', onClick: () => model.handlers.saveAssessment(assessmentAnswers(form, questions), form) }));
      actions.append(button('ثبت نهایی پاسخ‌ها', { variant: 'primary', type: 'submit' }));
      form.append(actions);
      panel.append(
        el('div', { className: 'ui-state ui-state-info', text: 'پاسخ صحیح در این مرحله به مرورگر ارسال نشده است. ذخیره و ثبت نهایی با revision فعلی همین تلاش روی سرور انجام می‌شود.' }),
        form
      );
      container.append(panel);
    }

  function renderAssessmentResult(container, assessment, attempt, result, model) {
      clear(container);
      const basisPoints = Number(result?.score_basis_points);
      const hasScore = Number.isFinite(basisPoints);
      const scoreText = hasScore ? `${faNumber(basisPoints / 100)}٪` : 'نتیجه ثبت شد';
      const panel = el('article', { className: 'detail-panel surface-card' },
        el('button', { className: 'text-link detail-panel__back', text: '‹ آزمون‌ها', attrs: { type: 'button' }, on: { click: model.handlers.closeAssessment } }),
        el('span', { className: 'assessment-card-v2__type', text: 'نتیجه آزمون' }),
        el('h3', { text: text(assessment?.title || attempt?.title) || 'آزمون' }),
        el('div', { className: 'metadata-row' },
          el('span', { text: `امتیاز: ${scoreText}` }),
          result?.correct_count != null && result?.question_count != null ? el('span', { text: `${faNumber(result.correct_count)} پاسخ صحیح از ${faNumber(result.question_count)}` }) : null
        )
      );
      const review = Array.isArray(result?.review) ? result.review : [];
      const questions = new Map((Array.isArray(attempt?.questions) ? attempt.questions : []).map((question) => [String(question.id), question]));
      if (review.length) {
        const reviewRoot = el('div', { className: 'assessment-review' });
        review.forEach((row, index) => {
          const question = questions.get(String(row.id)) || {};
          const choices = Array.isArray(question.choices) ? question.choices : [];
          const selected = Number.isInteger(Number(row.selected)) ? choices[Number(row.selected)] : '';
          const correct = Number.isInteger(Number(row.correct)) ? choices[Number(row.correct)] : '';
          const section = el('section', { className: 'assessment-review__item' },
            el('h4', { text: `${faNumber(index + 1)}. ${text(question.prompt) || `سؤال ${text(row.id)}`}` }),
            el('p', { text: row.is_correct ? 'پاسخ شما صحیح بود.' : 'پاسخ شما صحیح نبود.' })
          );
          if (selected) section.append(el('p', { text: `پاسخ شما: ${text(selected)}` }));
          if (correct) section.append(el('p', { text: `پاسخ صحیح: ${text(correct)}` }));
          if (row.explanation) section.append(el('p', { text: text(row.explanation) }));
          reviewRoot.append(section);
        });
        panel.append(reviewRoot);
      }
      panel.append(el('div', { className: 'ui-state ui-state-success', text: 'این نتیجه از scoring canonical سرور دریافت شده است و در مرورگر محاسبه نشده است.' }));
      container.append(panel);
    }

  Object.assign(UI, { renderResources, renderResourceCards, renderAssessments, renderAssessmentCards, renderGrades, renderGradeRows, renderResourceDetail, renderAssessmentDetail, renderAssessmentAttempt, renderAssessmentResult, assessmentAnswers });
  if (typeof module !== 'undefined' && module.exports) module.exports = UI;
})(typeof window !== 'undefined' ? window : globalThis);