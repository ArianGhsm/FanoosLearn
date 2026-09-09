(function (global) {
  'use strict';
  const UI = global.FanoosProductUI;
  if (!UI || !UI._internal) return;
  const { text, bounded, el, button, clear, faNumber, faDate, faDateTime, faTime, money, status, resourceType, assessmentType, resultRows, skeleton, stateBlock, partialState, heading, routeFromHash, routeHash, courseGroups, findCourse, courseLabelForId, localDateKey, todayKey, addNeutralDays, weekBounds, rowMatchesCourse, pageMeta, resourceTypes } = UI._internal;

  function renderAnnouncements(container, model) {
      if (!model.announcements?.ok) return stateBlock(container, 'اطلاعیه‌ها دریافت نشدند', 'صندوق اطلاعیه این فضای آموزشی فعلاً در دسترس نیست.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      const rows = resultRows(model.announcements);
      if (!rows.length) return stateBlock(container, 'اطلاعیه‌ای وجود ندارد', 'پیام‌های رسمی این فضای آموزشی پس از انتشار اینجا دیده می‌شوند.');
      const selected = model.selectedAnnouncement;
      if (selected) return renderAnnouncementDetail(container, selected, model);
      const list = el('div', { className: 'announcement-list' });
      rows.forEach((row) => {
        const unread = String(row.status || '').toLowerCase() !== 'read' && !row.read_at;
        list.append(el('button', { className: `announcement-row${unread ? ' announcement-row--unread' : ''}`, attrs: { type: 'button' }, on: { click: () => model.handlers.openAnnouncement(row) } },
          el('div', {}, el('h3', { text: text(row.title) || 'اطلاعیه' }), el('p', { text: bounded(row.body, 180) })),
          row.published_at ? el('time', { text: faDate(row.published_at, model.context) }) : null
        ));
      });
      container.append(list);
    }

  function renderAnnouncementDetail(container, row, model) {
      const unread = String(row.status || '').toLowerCase() !== 'read' && !row.read_at;
      const panel = el('article', { className: 'detail-panel surface-card' },
        el('button', { className: 'text-link detail-panel__back', text: '‹ اطلاعیه‌ها', attrs: { type: 'button' }, on: { click: model.handlers.closeAnnouncement } }),
        el('h3', { text: text(row.title) || 'اطلاعیه' }),
        el('div', { className: 'detail-panel__meta', text: row.published_at ? faDateTime(row.published_at, model.context) : '' }),
        el('div', { className: 'detail-panel__body', text: text(row.body) })
      );
      if (unread && model.handlers.markAnnouncementRead) panel.append(button('علامت‌گذاری به‌عنوان خوانده‌شده', { variant: 'secondary', onClick: () => model.handlers.markAnnouncementRead(row) }));
      container.append(panel);
    }

  function parseFormSchema(raw) {
      if (!raw) return null;
      if (typeof raw === 'object') return raw;
      try { const parsed = JSON.parse(String(raw)); return parsed && typeof parsed === 'object' ? parsed : null; } catch (_error) { return null; }
    }

  function schemaFields(schema) {
      if (!schema || typeof schema !== 'object') return [];
      const candidates = Array.isArray(schema.fields) ? schema.fields : Array.isArray(schema.questions) ? schema.questions : [];
      return candidates.filter((field) => field && typeof field === 'object' && text(field.id || field.key));
    }

  function renderForms(container, model) {
      if (!model.forms?.ok) return stateBlock(container, 'فرم‌ها دریافت نشدند', 'فهرست فرم‌های فعال فعلاً در دسترس نیست.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      const rows = resultRows(model.forms);
      if (model.selectedForm) return renderFormDetail(container, model.selectedForm, model);
      if (!rows.length) return stateBlock(container, 'فرم فعالی وجود ندارد', 'فرم‌هایی که برای این حساب و فضای آموزشی باز باشند اینجا نمایش داده می‌شوند.');
      const list = el('div', { className: 'form-list' });
      rows.forEach((row) => {
        const dates = [row.opens_at ? `شروع ${faDate(row.opens_at, model.context)}` : '', row.closes_at ? `مهلت ${faDate(row.closes_at, model.context)}` : ''].filter(Boolean).join(' · ');
        list.append(el('div', { className: 'form-row' },
          el('div', {}, el('h3', { text: text(row.title) || 'فرم' }), el('p', { text: text(row.description) || dates || 'فرم فعال' })),
          button('باز کردن', { variant: 'secondary', onClick: () => model.handlers.openForm(row) })
        ));
      });
      container.append(list);
    }

  function renderFormDetail(container, row, model) {
      const schema = parseFormSchema(row.schema_json);
      const fields = schemaFields(schema);
      const panel = el('section', { className: 'detail-panel surface-card' },
        el('button', { className: 'text-link detail-panel__back', text: '‹ فرم‌ها', attrs: { type: 'button' }, on: { click: model.handlers.closeForm } }),
        el('h3', { text: text(row.title) || 'فرم' })
      );
      if (row.description) panel.append(el('p', { className: 'detail-panel__body', text: text(row.description) }));
      if (!fields.length) {
        panel.append(el('div', { className: 'partial-state', text: 'ساختار قابل‌نمایش این فرم در projection فعلی موجود نیست؛ JSON خام نمایش داده نمی‌شود.' }));
        container.append(panel); return;
      }
      const form = el('form', { on: { submit: (event) => { event.preventDefault(); const answers = {}; fields.forEach((field) => { const key = text(field.id || field.key); const input = event.currentTarget.elements.namedItem(`field-${key}`); if (!input) return; if (typeof RadioNodeList !== 'undefined' && input instanceof RadioNodeList) answers[key] = input.value; else if (input.type === 'checkbox') answers[key] = !!input.checked; else answers[key] = input.value; }); model.handlers.submitForm(row, answers, event.currentTarget); } } });
      fields.forEach((field) => form.append(renderFormField(field)));
      form.append(button('ثبت پاسخ', { variant: 'primary', type: 'submit' }));
      panel.append(form); container.append(panel);
    }

  function renderFormField(field) {
      const id = text(field.id || field.key);
      const label = text(field.label || field.title || field.question) || 'پاسخ';
      const type = text(field.type || 'text').toLowerCase();
      const required = field.required === true;
      const wrapper = el('div', { className: 'field' }, el('label', { attrs: { for: `form-${id}` }, text: `${label}${required ? ' *' : ''}` }));
      if (type === 'textarea' || type === 'long_text') {
        wrapper.append(el('textarea', { attrs: { id: `form-${id}`, name: `field-${id}`, rows: '4', required: required || null } }));
      } else if (type === 'select' || type === 'choice') {
        const select = el('select', { attrs: { id: `form-${id}`, name: `field-${id}`, required: required || null } }, el('option', { text: 'انتخاب کنید', attrs: { value: '' } }));
        (Array.isArray(field.options) ? field.options : []).forEach((option) => {
          const value = typeof option === 'object' ? text(option.value || option.id || option.label) : text(option);
          const caption = typeof option === 'object' ? text(option.label || option.value) : text(option);
          if (value) select.append(el('option', { text: caption || value, attrs: { value } }));
        });
        wrapper.append(select);
      } else if (type === 'checkbox' || type === 'boolean') {
        wrapper.append(el('input', { attrs: { id: `form-${id}`, name: `field-${id}`, type: 'checkbox', required: required || null } }));
      } else {
        const htmlType = ['number', 'email', 'date'].includes(type) ? type : 'text';
        wrapper.append(el('input', { attrs: { id: `form-${id}`, name: `field-${id}`, type: htmlType, required: required || null } }));
      }
      if (field.help || field.description) wrapper.append(el('small', { text: text(field.help || field.description) }));
      return wrapper;
    }

  function orderStatus(value) {
      const key = String(value || '').trim().toLowerCase();
      return key === 'pending' ? 'در انتظار پرداخت' : status(value);
    }

  function renderOrders(container, model) {
      if (!model.orders?.ok) return stateBlock(container, 'سفارش‌ها دریافت نشدند', 'وضعیت خرید و دسترسی فعلاً در دسترس نیست.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      const rows = resultRows(model.orders);
      const notice = el('div', { className: 'ui-state ui-state-info', text: 'در projection فعلی، فهرست کالاهای قابل خرید به‌صورت عمومی ارائه نشده است. این صفحه فقط سفارش‌ها و وضعیت قطعی بازگشتی از سرور را نمایش می‌دهد.' });
      container.append(notice);
      if (!rows.length) return container.append(el('div', { className: 'state-block' }, el('div', { className: 'state-block__inner' }, el('h3', { text: 'هنوز سفارشی ثبت نشده است' }), el('p', { text: 'محصول یا شناسه فنی برای خرید دستی از شما دریافت نمی‌شود.' }))));
      const list = el('div', { className: 'order-list' });
      rows.forEach((row) => {
        const amount = money(row.total_minor ?? row.amount_minor, row.currency);
        list.append(el('div', { className: 'order-row' },
          el('div', {}, el('h3', { text: text(row.product_name_snapshot || row.title) || 'سفارش' }), el('p', { text: [orderStatus(row.status), row.created_at ? faDate(row.created_at, model.context) : ''].filter(Boolean).join(' · ') })),
          el('div', { className: 'order-amount-v2' }, document.createTextNode(amount), row.entitlement_status ? el('small', { text: status(row.entitlement_status) }) : el('small', { text: 'دسترسی مستقل از پرداخت بررسی می‌شود' }))
        ));
      });
      container.append(list);
    }

  function renderSearch(container, model) {
      const q = text(model.route.query.q || '');
      if (q.length < 2) return stateBlock(container, 'عبارت جست‌وجو را وارد کنید', 'حداقل دو نویسه برای جست‌وجو لازم است.');
      if (!model.search?.ok) return stateBlock(container, 'جست‌وجو انجام نشد', 'ارتباط با جست‌وجوی فضای آموزشی برقرار نشد.', { actionLabel: 'تلاش دوباره', onAction: model.handlers.retry });
      const rows = resultRows(model.search);
      if (!rows.length) return stateBlock(container, 'نتیجه‌ای پیدا نشد', `برای «${bounded(q, 80)}» موردی در دسترسی فعلی شما پیدا نشد.`);
      const labels = { course: 'درس', resource: 'منبع', announcement: 'اطلاعیه', form: 'فرم', assessment: 'آزمون', schedule: 'برنامه' };
      const list = el('div', { className: 'search-results' });
      rows.forEach((row) => list.append(el('div', { className: 'search-result' },
        el('div', {}, el('h3', { text: text(row.title) || 'نتیجه' }), row.excerpt || row.context ? el('p', { text: bounded(row.excerpt || row.context, 160) }) : null),
        el('span', { className: 'search-result__type', text: labels[String(row.source_type || '').toLowerCase()] || 'نتیجه' })
      )));
      container.append(list);
    }

  Object.assign(UI, { renderAnnouncements, renderAnnouncementDetail, parseFormSchema, schemaFields, renderForms, renderFormDetail, renderFormField, orderStatus, renderOrders, renderSearch });
  if (typeof module !== 'undefined' && module.exports) module.exports = UI;
})(typeof window !== 'undefined' ? window : globalThis);
