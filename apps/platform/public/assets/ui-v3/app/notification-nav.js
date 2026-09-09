import { icon } from '../shell/ui.js';

const ROOT_ID = 'fanoos-v3-root';
const ROUTE = '#/notifications';

function isActive() {
  const hash = String(window.location.hash || '');
  return hash === ROUTE || hash.startsWith(`${ROUTE}?`);
}

function navigate() {
  if (!isActive()) window.location.hash = ROUTE;
}

function decorate(button, locked) {
  button.type = 'button';
  button.disabled = Boolean(locked);
  if (isActive()) button.setAttribute('aria-current', 'page');
  button.addEventListener('click', navigate);
  return button;
}

function desktopButton(locked) {
  const button = document.createElement('button');
  button.className = `f3-shell-nav-item f3-shell-nav-item--secondary${isActive() ? ' is-active' : ''}`;
  button.dataset.f3NotificationsDesktop = 'true';
  button.append(icon('announcement'));
  const label = document.createElement('span');
  label.textContent = 'اعلان‌های شخصی';
  button.append(label);
  return decorate(button, locked);
}

function moreButton(locked) {
  const button = document.createElement('button');
  button.className = 'f3-shell-more-link';
  button.dataset.f3NotificationsMorePage = 'true';
  button.append(icon('announcement'));
  const label = document.createElement('span');
  label.textContent = 'اعلان‌های شخصی';
  button.append(label, icon('chevron'));
  return decorate(button, locked);
}

function install(event) {
  const root = document.getElementById(ROOT_ID);
  if (!(root instanceof Element)) return;
  const locked = !event?.detail?.workspaceId;

  const secondary = root.querySelector('.f3-shell-sidebar__secondary');
  if (secondary && !secondary.querySelector('[data-f3-notifications-desktop]')) {
    secondary.append(desktopButton(locked));
  }

  root.querySelectorAll('.f3-shell-more-page__groups .f3-shell-more-group').forEach((group) => {
    if (group.querySelector('[data-f3-notifications-more-page]')) return;
    const heading = group.querySelector('h3');
    if (heading?.textContent?.trim() === 'آموزش و پیگیری') group.append(moreButton(locked));
  });
}

const root = document.getElementById(ROOT_ID);
if (root instanceof Element) root.addEventListener('fanoos:v3:shell-ready', install);
