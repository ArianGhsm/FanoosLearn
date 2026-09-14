/*
 * Home: choosing a workspace.
 *
 * Only runs when the server rendered the "pick a workspace" variant, or when
 * the visitor explicitly asked to switch. Selecting one is a server-side
 * change to the session, so the page reloads afterwards and every subsequent
 * page is rendered for the new workspace.
 */
import { api, describeError } from '../foundation/api.js';

const list = document.getElementById('workspace-list');
if (list) {
    load();
}

function text(tag, className, value) {
    const node = document.createElement(tag);
    if (className) node.className = className;
    node.textContent = value;
    return node;
}

function renderEmpty() {
    const wrap = document.createElement('div');
    wrap.className = 'f-empty';
    wrap.append(
        text('div', 'f-empty__title', 'هنوز عضو هیچ کلاسی نیستی'),
        text('p', '', 'عضویت از داخل ربات فانوس انجام می‌شود. وقتی نماینده تأییدت کرد، کلاس همین‌جا ظاهر می‌شود.'),
    );
    list.replaceChildren(wrap);
}

function renderError(message, retry) {
    const wrap = document.createElement('div');
    wrap.className = 'f-notice f-notice--error';
    const body = document.createElement('div');
    body.className = 'f-notice__body';
    body.append(text('div', 'f-notice__title', 'فهرست کلاس‌ها خوانده نشد'), text('p', '', message));
    if (retry) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'f-btn f-btn--ghost';
        button.textContent = 'تلاش دوباره';
        button.addEventListener('click', load);
        body.append(button);
    }
    wrap.append(body);
    list.replaceChildren(wrap);
}

function renderChoices(workspaces) {
    const items = workspaces.map((workspace) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'f-home__workspace';
        button.append(
            text('strong', '', String(workspace.name || 'کلاس بی‌نام')),
            text('span', 'f-muted', [workspace.institution_name, workspace.program_name, workspace.cohort_label]
                .filter(Boolean).join(' · ')),
        );
        button.addEventListener('click', () => select(workspace.id, button));
        return button;
    });

    const wrap = document.createElement('div');
    wrap.className = 'f-home__workspaces';
    wrap.append(...items);
    list.replaceChildren(wrap);
}

async function load() {
    list.setAttribute('aria-busy', 'true');
    list.replaceChildren(text('p', 'f-muted', 'در حال خواندن فهرست…'));
    try {
        const account = await api.get('/account');
        const workspaces = Array.isArray(account?.workspaces) ? account.workspaces : [];
        if (workspaces.length === 0) {
            renderEmpty();
            return;
        }
        renderChoices(workspaces);
    } catch (error) {
        renderError(describeError(error), true);
    } finally {
        list.setAttribute('aria-busy', 'false');
    }
}

async function select(workspaceId, button) {
    const previous = button.textContent;
    button.disabled = true;
    try {
        await api.post('/workspaces/select', { workspace_id: String(workspaceId) });
        window.location.assign('/app');
    } catch (error) {
        button.disabled = false;
        button.textContent = previous;
        renderError(describeError(error), false);
    }
}
