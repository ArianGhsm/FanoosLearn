(function (global) {
  'use strict';
  const UI = global.FanoosProductUI;
  if (!UI || !UI._internal) return;
  const { text, bounded, el, button, clear, faNumber, faDate, faDateTime, faTime, money, status, resourceType, assessmentType, resultRows, skeleton, stateBlock, partialState, heading, routeFromHash, routeHash, courseGroups, findCourse, courseLabelForId, localDateKey, todayKey, addNeutralDays, weekBounds, rowMatchesCourse, pageMeta, resourceTypes } = UI._internal;

  function renderAccount(container, model) {
      clear(container);
      const account = model.account || {};
      const user = account.user || {};
      const workspaces = Array.isArray(account.workspaces) ? account.workspaces : [];
      const layout = el('div', { className: 'account-layout' });
      const profile = el('section', { className: 'account-card surface-card' }, el('h3', { text: 'حساب' }));
      const dl = el('dl', { className: 'definition-list' });
      const pairs = [
        ['نام', text(user.display_name || user.name) || '—'],
        ['شناسه ورود', text(user.identifier || user.primary_identifier) || '—'],
        ['فضای فعال', model.context.workspaceName || '—'],
      ];
      pairs.forEach(([label, value]) => dl.append(el('div', { className: 'definition-row' }, el('dt', { text: label }), el('dd', { text: value }))));
      profile.append(dl); layout.append(profile);
      const spaces = el('section', { className: 'account-card surface-card' }, el('h3', { text: 'فضاهای آموزشی' }));
      const list = el('div', { className: 'workspace-list' });
      workspaces.forEach((workspace) => {
        const active = String(workspace.id || '') === String(account.selected_workspace_id || '');
        list.append(el('div', { className: 'workspace-row' },
          el('div', {}, el('strong', { text: text(workspace.name || workspace.label || workspace.slug) || 'فضای آموزشی' }), workspace.path_label ? el('small', { text: text(workspace.path_label) }) : null),
          active ? el('span', { className: 'status-chip status-chip-success', text: 'فعال' }) : button('انتخاب', { variant: 'quiet', onClick: () => model.handlers.switchWorkspace(workspace.id) })
        ));
      });
      spaces.append(list); layout.append(spaces); container.append(layout);
    }

  function renderManagement(container, model) {
      clear(container);
      if (!model.management?.ok) return stateBlock(container, 'دسترسی مدیریتی موجود نیست', 'این صفحه فقط پس از تأیید مجوز از backend نمایش داده می‌شود.');
      const data = model.management.data || {};
      const sections = data.sections && typeof data.sections === 'object' ? data.sections : {};
      const labels = {
        members: ['اعضا', 'مشاهده اعضای فضای آموزشی'],
        announcements: ['اطلاعیه‌ها', 'انتشار و مدیریت اطلاعیه‌ها'],
        forms: ['فرم‌ها', 'مدیریت فرم‌های فضای آموزشی'],
        resources: ['محتوا', 'تولید، بررسی و انتشار منابع'],
        assessments: ['آزمون‌ها', 'مدیریت آزمون‌ها و ارزیابی‌ها'],
        grades: ['نمرات', 'عملیات مجاز نمره‌ها'],
        orders: ['سفارش‌ها', 'وضعیت سفارش‌های مجاز'],
        audit_events: ['رویدادهای ممیزی', 'نمای مدیریتی مجاز'],
      };
      const grid = el('div', { className: 'management-grid' });
      Object.entries(sections).forEach(([key, value]) => {
        const label = labels[key] || [key === 'workspace' ? 'فضای آموزشی' : 'بخش مدیریتی', 'داده مجاز backend'];
        const card = el('section', { className: 'management-card surface-card' }, el('h3', { text: label[0] }), el('p', { text: label[1] }));
        if (typeof value === 'number' || /^\d+$/.test(String(value))) card.append(el('strong', { className: 'management-stat', text: faNumber(value) }));
        else if (Array.isArray(value)) card.append(el('strong', { className: 'management-stat', text: faNumber(value.length) }));
        else card.append(el('span', { className: 'status-chip status-chip-info', text: 'دسترسی فعال' }));
        grid.append(card);
      });
      if (!grid.childNodes.length) grid.append(el('section', { className: 'management-card surface-card' }, el('h3', { text: 'مدیریت فضای آموزشی' }), el('p', { text: 'مجوز مدیریت تأیید شده است؛ endpoint فعلی جزئیات بیشتری برای این حساب ارائه نکرد.' })));
      container.append(grid);
    }

  Object.assign(UI, { renderAccount, renderManagement });
  if (typeof module !== 'undefined' && module.exports) module.exports = UI;
})(typeof window !== 'undefined' ? window : globalThis);
