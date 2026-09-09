(function (global) {
  'use strict';
  const UI = global.FanoosProductUI;
  if (!UI || !UI._internal) return;
  const { text, bounded, el, button, clear, faNumber, faDate, faDateTime, faTime, money, status, resourceType, assessmentType, resultRows, skeleton, stateBlock, partialState, heading, routeFromHash, routeHash, courseGroups, findCourse, courseLabelForId, localDateKey, todayKey, addNeutralDays, weekBounds, rowMatchesCourse, pageMeta, resourceTypes } = UI._internal;

  function renderHome(container, model) {
      clear(container);
      const { parts, context, handlers } = model;
      const schedule = resultRows(parts.schedule);
      const assessments = resultRows(parts.assessments);
      const announcements = resultRows(parts.announcements);
      const resources = resultRows(parts.resources);
      const grades = resultRows(parts.grades);
      const now = Date.now();
      const futureSchedule = schedule.filter((row) => {
        const raw = String(row.starts_at || '');
        const parsed = new Date(/^\d{4}-\d{2}-\d{2} /.test(raw) ? `${raw.replace(' ', 'T')}Z` : raw);
        return Number.isFinite(parsed.getTime()) && parsed.getTime() >= now - 90 * 60000;
      });
      const next = futureSchedule[0] || schedule[0] || null;
  
      const overview = el('div', { className: 'home-overview' });
      const today = el('section', { className: 'today-card surface-card' });
      const todayTop = el('div', { className: 'today-card__top' },
        el('div', {}, el('span', { className: 'today-card__label', text: 'امروز در فضای آموزشی' }), el('h3', { text: context.workspaceName || 'فضای آموزشی' })),
        el('span', { className: 'today-card__date', text: context.todayLabel || '' })
      );
      today.append(todayTop);
      if (parts.schedule && !parts.schedule.ok) {
        today.append(partialState('برنامه امروز در دسترس نیست.', handlers.retryHome));
      } else if (!next) {
        today.append(el('div', { className: 'next-event' }, el('strong', { text: 'برنامه‌ای برای این بازه ثبت نشده است.' }), el('p', { className: 'page-context__subtitle', text: 'برای دیدن بازه‌های دیگر به بخش برنامه برو.' })));
      } else {
        const meta = el('div', { className: 'next-event__meta' });
        if (next.course_title) meta.append(el('span', { text: text(next.course_title) }));
        if (next.location_text) meta.append(el('span', { text: text(next.location_text) }));
        today.append(el('div', { className: 'next-event' },
          el('span', { className: 'next-event__time', text: faTime(next.starts_at, context) }),
          el('div', { className: 'next-event__title', text: text(next.title || next.course_title) || 'رویداد آموزشی' }),
          meta,
          button('مشاهده برنامه', { variant: 'quiet', onClick: () => handlers.navigate('schedule') })
        ));
      }
      overview.append(today);
  
      const attention = el('div', { className: 'home-attention' });
      const activeAssessment = assessments[0];
      attention.append(el('section', { className: 'attention-card surface-card' },
        el('span', { className: 'attention-card__label', text: 'آزمون و تمرین' }),
        el('strong', { className: 'attention-card__value', text: activeAssessment ? text(activeAssessment.title) : 'مورد فعالی ثبت نشده' }),
        el('span', { className: 'attention-card__meta', text: activeAssessment ? assessmentType(activeAssessment.assessment_kind || activeAssessment.type_key) : 'فهرست آزمون‌ها را بررسی کنید' })
      ));
      const unread = announcements.filter((row) => String(row.status || '').toLowerCase() !== 'read' && !row.read_at).length;
      attention.append(el('section', { className: 'attention-card surface-card' },
        el('span', { className: 'attention-card__label', text: 'اطلاعیه‌ها' }),
        el('strong', { className: 'attention-card__value', text: unread ? `${faNumber(unread)} اطلاعیه خوانده‌نشده` : 'اطلاعیه خوانده‌نشده ندارید' }),
        el('span', { className: 'attention-card__meta', text: announcements[0] ? bounded(announcements[0].title, 70) : '—' })
      ));
      overview.append(attention);
      container.append(overview);
  
      const quickSection = el('section', { className: 'product-section' }, heading('دسترسی سریع', 'مسیرهای پرتکرار'));
      const quick = el('div', { className: 'quick-actions' });
      [
        ['درس‌ها', 'جلسات و محتوای هر درس', 'courses'],
        ['برنامه هفته', 'کلاس‌ها و رویدادها', 'schedule'],
        ['منابع اخیر', 'جزوه، خلاصه و بانک سؤال', 'resources'],
        ['نمرات من', 'نمره‌های منتشرشده', 'grades'],
      ].forEach(([title, caption, route]) => quick.append(el('button', { className: 'quick-action', attrs: { type: 'button' }, on: { click: () => handlers.navigate(route) } }, el('strong', { text: title }), el('small', { text: caption }))));
      quickSection.append(quick);
      container.append(quickSection);
  
      const grid = el('div', { className: 'home-grid' });
      grid.append(renderHomeListPanel('برنامه پیش رو', futureSchedule.slice(0, 4), parts.schedule, (row) => ({
        title: text(row.title || row.course_title) || 'رویداد آموزشی',
        meta: [faDate(row.starts_at, context), text(row.location_text)].filter(Boolean).join(' · '),
        side: faTime(row.starts_at, context),
      }), () => handlers.navigate('schedule')));
      grid.append(renderHomeListPanel('آخرین اطلاعیه‌ها', announcements.slice(0, 4), parts.announcements, (row) => ({
        title: text(row.title) || 'اطلاعیه',
        meta: bounded(row.body, 90),
        side: faDate(row.published_at, context),
      }), () => handlers.navigate('announcements')));
      grid.append(renderHomeListPanel('منابع تازه', resources.slice(0, 4), parts.resources, (row) => ({
        title: text(row.title) || 'منبع آموزشی',
        meta: resourceType(row.type_key || row.type),
        side: row.current_version_no ? `نسخه ${faNumber(row.current_version_no)}` : '',
      }), () => handlers.navigate('resources')));
      grid.append(renderHomeListPanel('آخرین نمره‌ها', grades.slice(0, 4), parts.grades, (row) => ({
        title: text(row.item_title || row.gradebook_title) || 'نمره',
        meta: text(row.course_title),
        side: row.score != null ? `${faNumber(row.score)}${row.max_score != null ? ` / ${faNumber(row.max_score)}` : ''}` : '—',
      }), () => handlers.navigate('grades')));
      container.append(grid);
    }

  function renderHomeListPanel(title, rows, sourceResult, project, onMore) {
      const panel = el('section', { className: 'home-panel surface-card' });
      panel.append(el('div', { className: 'home-panel__heading' }, el('h3', { text: title }), el('button', { className: 'text-link', text: 'مشاهده همه', attrs: { type: 'button' }, on: { click: onMore } })));
      if (sourceResult && !sourceResult.ok) {
        panel.append(partialState('این بخش فعلاً در دسترس نیست.'));
        return panel;
      }
      if (!rows.length) {
        panel.append(el('div', { className: 'partial-state', text: 'موردی برای نمایش وجود ندارد.' }));
        return panel;
      }
      const list = el('div', { className: 'home-list' });
      rows.forEach((row) => {
        const item = project(row);
        list.append(el('div', { className: 'home-list-item' },
          el('div', {}, el('strong', { text: item.title }), item.meta ? el('small', { text: item.meta }) : null),
          item.side ? el('span', { className: 'home-list-item__side', text: item.side }) : null
        ));
      });
      panel.append(list);
      return panel;
    }

  function renderCourses(container, model) {
      clear(container);
      const rows = model.academics?.ok ? (model.academics.data?.courses || []) : [];
      if (!model.academics?.ok) return stateBlock(container, 'درس‌ها دریافت نشدند', 'ارتباط با اطلاعات آموزشی این فضا برقرار نشد.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      const courses = courseGroups(rows);
      const requested = model.route.query.course || '';
      if (requested) {
        const course = findCourse(courses, requested);
        if (!course) return stateBlock(container, 'درس پیدا نشد', 'این درس در فضای آموزشی فعال وجود ندارد یا دیگر در دسترس نیست.', { actionLabel: 'بازگشت به درس‌ها', onAction: () => model.handlers.navigate('courses') });
        return renderCourseDetail(container, course, courses, model);
      }
      if (!courses.length) return stateBlock(container, 'هنوز درسی ثبت نشده است', 'پس از اضافه‌شدن درس‌ها به فضای آموزشی، اینجا نمایش داده می‌شوند.');
  
      const terms = [...new Set(courses.flatMap((course) => [...course.terms]))];
      const selectedTerm = model.route.query.term || '';
      if (terms.length > 1) {
        const bar = el('div', { className: 'filter-bar' });
        const field = el('div', { className: 'filter-field' }, el('label', { text: 'ترم' }));
        const select = el('select', { attrs: { 'aria-label': 'فیلتر ترم' }, on: { change: (event) => model.handlers.navigate('courses', event.target.value ? { term: event.target.value } : {}) } }, el('option', { text: 'همه ترم‌ها', attrs: { value: '' } }));
        terms.forEach((term) => select.append(el('option', { text: term, attrs: { value: term, selected: term === selectedTerm || null } })));
        field.append(select); bar.append(field); container.append(bar);
      }
      const visible = selectedTerm ? courses.filter((course) => course.terms.has(selectedTerm)) : courses;
      const grid = el('div', { className: 'course-grid' });
      visible.forEach((course) => {
        const nextSession = course.sessions.find((session) => session.startsAt) || course.sessions[0];
        const card = el('button', { className: 'course-card', attrs: { type: 'button' }, on: { click: () => model.handlers.navigate('courses', { course: course.code }) } });
        const top = el('div', { className: 'course-card__top' }, el('div', {}, el('h3', { text: course.title })));
        if (course.credit != null) top.append(el('span', { className: 'status-chip status-chip-info', text: `${faNumber(course.credit)} واحد` }));
        card.append(top);
        if (course.code) card.append(el('bdi', { className: 'course-code', text: course.code, attrs: { dir: 'ltr' } }));
        const meta = el('div', { className: 'course-card__meta' });
        [...course.terms].slice(0, 2).forEach((term) => meta.append(el('span', { text: term })));
        meta.append(el('span', { text: `${faNumber(course.sessions.length)} جلسه` }));
        card.append(meta);
        if (nextSession) card.append(el('div', { className: 'course-card__next' }, el('span', { text: 'جلسه ثبت‌شده: ' }), el('strong', { text: nextSession.title })));
        grid.append(card);
      });
      container.append(grid);
    }

  function renderCourseDetail(container, course, courses, model) {
      const schedule = resultRows(model.parts?.schedule).filter((row) => rowMatchesCourse(row, course));
      const resources = resultRows(model.parts?.resources).filter((row) => rowMatchesCourse(row, course));
      const assessments = resultRows(model.parts?.assessments).filter((row) => rowMatchesCourse(row, course));
      const grades = resultRows(model.parts?.grades).filter((row) => rowMatchesCourse(row, course));
      const tab = ['overview', 'sessions', 'schedule', 'resources', 'assessments', 'grades'].includes(model.route.query.tab) ? model.route.query.tab : 'overview';
      const header = el('section', { className: 'course-detail-header surface-card' },
        el('div', {}, el('button', { className: 'text-link', text: '‹ همه درس‌ها', attrs: { type: 'button' }, on: { click: () => model.handlers.navigate('courses') } }), el('h3', { text: course.title }), course.code ? el('p', { text: course.code, attrs: { dir: 'ltr' } }) : null),
        el('div', { className: 'course-stat-strip' },
          stat(course.sessions.length, 'جلسه'), stat(resources.length, 'منبع'), stat(assessments.length, 'آزمون'), stat(grades.length, 'نمره')
        )
      );
      container.append(header);
      const tabs = el('div', { className: 'tabs', attrs: { role: 'tablist', 'aria-label': `بخش‌های ${course.title}` } });
      [
        ['overview', 'نمای کلی'], ['sessions', 'جلسات'], ['schedule', 'برنامه'], ['resources', 'منابع'], ['assessments', 'آزمون‌ها'], ['grades', 'نمرات'],
      ].forEach(([key, label]) => {
        const unavailable = (key === 'schedule' && model.parts?.schedule && !model.parts.schedule.ok)
          || (key === 'resources' && model.parts?.resources && !model.parts.resources.ok)
          || (key === 'assessments' && model.parts?.assessments && !model.parts.assessments.ok)
          || (key === 'grades' && model.parts?.grades && !model.parts.grades.ok);
        if (!unavailable) tabs.append(el('button', { text: label, attrs: { type: 'button', role: 'tab', 'aria-selected': key === tab ? 'true' : 'false' }, on: { click: () => model.handlers.navigate('courses', { course: course.code, tab: key }) } }));
      });
      container.append(tabs);
  
      if (tab === 'sessions') return renderSessionList(container, course.sessions, model.context);
      if (tab === 'schedule') return renderScheduleRows(container, schedule, model.context, model.handlers);
      if (tab === 'resources') return renderResourceCards(container, resources, courses, model);
      if (tab === 'assessments') return renderAssessmentCards(container, assessments, courses, model);
      if (tab === 'grades') return renderGradeRows(container, grades, model.context);
  
      const overview = el('div', { className: 'home-grid' });
      const next = schedule[0] || null;
      const sessionPanel = el('section', { className: 'home-panel surface-card' }, el('div', { className: 'home-panel__heading' }, el('h3', { text: 'جلسه بعدی / برنامه' })));
      sessionPanel.append(next ? el('div', { className: 'home-list-item' }, el('div', {}, el('strong', { text: text(next.title || next.course_title) }), el('small', { text: [faDateTime(next.starts_at, model.context), text(next.location_text)].filter(Boolean).join(' · ') }))) : el('div', { className: 'partial-state', text: 'رویداد آینده‌ای برای این درس در بازه فعلی ثبت نشده است.' }));
      overview.append(sessionPanel);
      overview.append(renderHomeListPanel('منابع درس', resources.slice(0, 3), model.parts?.resources, (row) => ({ title: text(row.title), meta: resourceType(row.type_key || row.type), side: row.current_version_no ? `نسخه ${faNumber(row.current_version_no)}` : '' }), () => model.handlers.navigate('courses', { course: course.code, tab: 'resources' })));
      overview.append(renderHomeListPanel('آزمون‌های درس', assessments.slice(0, 3), model.parts?.assessments, (row) => ({ title: text(row.title), meta: assessmentType(row.assessment_kind || row.type), side: row.max_attempts ? `${faNumber(row.max_attempts)} تلاش` : '' }), () => model.handlers.navigate('courses', { course: course.code, tab: 'assessments' })));
      overview.append(renderHomeListPanel('نمرات درس', grades.slice(0, 3), model.parts?.grades, (row) => ({ title: text(row.item_title || row.gradebook_title), meta: text(row.gradebook_title), side: row.score != null ? `${faNumber(row.score)}${row.max_score != null ? ` / ${faNumber(row.max_score)}` : ''}` : '—' }), () => model.handlers.navigate('courses', { course: course.code, tab: 'grades' })));
      container.append(overview);
    }

  function stat(value, label) { return el('div', { className: 'course-stat' }, el('strong', { text: faNumber(value) }), el('span', { text: label })); }

  function renderSessionList(container, sessions, context) {
      if (!sessions.length) return container.append(el('div', { className: 'partial-state', text: 'جلسه‌ای برای این درس ثبت نشده است.' }));
      const list = el('div', { className: 'form-list' });
      [...sessions].sort((a, b) => Number(a.sequence || 0) - Number(b.sequence || 0)).forEach((session) => {
        list.append(el('div', { className: 'form-row' },
          el('div', {}, el('h3', { text: session.title }), el('p', { text: session.startsAt ? faDateTime(session.startsAt, context) : 'زمان مشخص نشده' })),
          session.status ? el('span', { className: 'status-chip status-chip-info', text: status(session.status) }) : null
        ));
      });
      container.append(list);
    }

  function renderSchedule(container, model) {
      if (!model.schedule?.ok) return stateBlock(container, 'برنامه دریافت نشد', 'برنامه این فضای آموزشی فعلاً در دسترس نیست.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      const mode = ['today', 'week', 'upcoming'].includes(model.route.subview) ? model.route.subview : 'today';
      const segmented = el('div', { className: 'segmented-control', attrs: { role: 'group', 'aria-label': 'بازه برنامه' } });
      [['today', 'امروز'], ['week', 'هفته'], ['upcoming', 'پیش رو']].forEach(([key, label]) => segmented.append(el('button', { text: label, attrs: { type: 'button', 'aria-pressed': key === mode ? 'true' : 'false' }, on: { click: () => model.handlers.navigate('schedule', {}, key) } })));
      container.append(el('div', { className: 'product-section' }, segmented));
      const rows = resultRows(model.schedule);
      const timezone = model.context.workspaceTimezone;
      const today = todayKey(timezone);
      const bounds = weekBounds(timezone);
      let visible = rows;
      if (mode === 'today') visible = rows.filter((row) => localDateKey(row.starts_at, timezone) === today);
      if (mode === 'week') visible = rows.filter((row) => { const key = localDateKey(row.starts_at, timezone); return key >= bounds.start && key < bounds.end; });
      if (mode === 'upcoming') visible = rows.filter((row) => localDateKey(row.starts_at, timezone) >= today);
      renderScheduleRows(container, visible, model.context, model.handlers);
    }

  function renderScheduleRows(container, rows, context, handlers) {
      if (!rows.length) return container.append(el('div', { className: 'state-block' }, el('div', { className: 'state-block__inner' }, el('h3', { text: 'برنامه‌ای برای این بازه ثبت نشده است.' }), el('p', { text: 'بازه دیگری را انتخاب کنید یا بعداً دوباره بررسی کنید.' }))));
      const groups = new Map();
      rows.forEach((row) => { const key = localDateKey(row.starts_at, context.workspaceTimezone) || 'unknown'; if (!groups.has(key)) groups.set(key, []); groups.get(key).push(row); });
      const timeline = el('div', { className: 'timeline' });
      [...groups.entries()].sort(([a], [b]) => a.localeCompare(b)).forEach(([, items]) => {
        const first = items[0];
        const group = el('section', { className: 'timeline-group' }, el('div', { className: 'timeline-date', text: faDate(first.starts_at, context) }));
        const list = el('div', { className: 'timeline-items' });
        items.forEach((row) => {
          const meta = [text(row.course_title), text(row.location_text)].filter(Boolean).join(' · ');
          const type = text(row.event_type);
          list.append(el('div', { className: 'timeline-item' },
            el('div', {}, el('h3', {}, type ? el('span', { className: 'event-type', text: type === 'exam' ? 'آزمون' : type === 'deadline' ? 'مهلت' : '' }) : null, document.createTextNode(text(row.title || row.course_title) || 'رویداد آموزشی')), meta ? el('p', { text: meta }) : null),
            el('div', { className: 'timeline-time', text: `${faTime(row.starts_at, context)}${row.ends_at ? ` – ${faTime(row.ends_at, context)}` : ''}` })
          ));
        });
        group.append(list); timeline.append(group);
      });
      container.append(timeline);
    }

  Object.assign(UI, { renderHome, renderHomeListPanel, renderCourses, renderCourseDetail, stat, renderSessionList, renderSchedule, renderScheduleRows });
  if (typeof module !== 'undefined' && module.exports) module.exports = UI;
})(typeof window !== 'undefined' ? window : globalThis);
