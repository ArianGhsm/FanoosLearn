(function (root, factory) {
    if (!root || !root.ClassOpsOps) return;
    root.ClassOpsOps.mountOperationsCenter = factory(root.ClassOpsOps);
})(typeof globalThis !== 'undefined' ? globalThis : this, function (core) {
    'use strict';
    const {ACTIONS, FOUNDATION_CAPABILITIES, ClassOpsClient, buildIntent, confirmIntent, diffItem, capabilityModel} = core;
    function nonce() {
        if (typeof crypto !== 'undefined' && crypto.randomUUID) return crypto.randomUUID();
        return 'intent-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 12);
    }

    function qs(id) { return typeof document === 'undefined' ? null : document.getElementById(id); }
    function text(node, value) { if (node) node.textContent = value; }
    function hidden(node, value) { if (node) node.hidden = !!value; }

    async function mountOperationsCenter(options) {
        if (typeof document === 'undefined') return null;
        const opts = options || {};
        const client = opts.client || new ClassOpsClient(opts.clientOptions);
        const access = qs('classops-access-state');
        const ownerPanel = qs('classops-owner-center');
        const listNode = qs('classops-item-list');
        const itemState = qs('classops-item-state');
        const capabilityGrid = qs('classops-capabilities');
        const conflict = qs('classops-conflict');
        const empty = qs('classops-empty');
        const dialog = qs('classops-confirm-dialog');
        const dialogText = qs('classops-confirm-text');
        const dialogConfirm = qs('classops-confirm-yes');
        let selectedItem = null;
        let pendingMutation = null;

        function setState(kind, message) {
            if (access) access.dataset.state = kind;
            text(access, message);
        }
        function renderCapabilities(capabilities) {
            if (!capabilityGrid) return;
            capabilityGrid.innerHTML = '';
            capabilityModel('owner', capabilities).forEach((entry) => {
                const card = document.createElement('article');
                card.className = 'classops-capability';
                card.dataset.enabled = entry.enabled ? 'true' : 'false';
                const title = document.createElement('strong');
                title.textContent = entry.label;
                const status = document.createElement('span');
                status.textContent = entry.enabled ? 'آماده در این branch' : 'در انتظار integration';
                card.append(title, status);
                capabilityGrid.appendChild(card);
            });
        }
        function renderList(items) {
            if (!listNode) return;
            listNode.innerHTML = '';
            hidden(empty, items.length !== 0);
            items.forEach((item) => {
                const button = document.createElement('button');
                button.type = 'button';
                button.className = 'classops-item-row';
                button.innerHTML = '<strong></strong><span></span>';
                button.querySelector('strong').textContent = item.title || 'بدون عنوان';
                button.querySelector('span').textContent = (item.type || '') + ' · ' + (item.status || '') + ' · r' + String(item.revision || 0);
                button.addEventListener('click', () => selectItem(item));
                listNode.appendChild(button);
            });
        }
        function selectItem(item) {
            selectedItem = item;
            text(qs('classops-selected-title'), item.title || 'بدون عنوان');
            text(qs('classops-selected-meta'), (item.type || '') + ' · ' + (item.status || '') + ' · revision ' + String(item.revision || 0));
            const editor = qs('classops-edit-description');
            if (editor) editor.value = item.description || '';
            hidden(qs('classops-selected'), false);
            const edit = qs('classops-edit');
            const schedule = qs('classops-schedule');
            const activate = qs('classops-activate');
            const cancel = qs('classops-cancel');
            const archive = qs('classops-archive');
            if (edit) edit.disabled = item.status === 'archived';
            if (schedule) schedule.disabled = item.status !== 'draft';
            if (activate) activate.disabled = item.status !== 'scheduled';
            if (cancel) cancel.disabled = !['draft', 'scheduled', 'active'].includes(item.status);
            if (archive) archive.disabled = item.status === 'archived';
        }
        async function refresh() {
            text(itemState, 'در حال دریافت فهرست...');
            hidden(conflict, true);
            try {
                const payload = await client.list({limit:50});
                const items = (payload.data && payload.data.items) || [];
                renderList(items);
                if (selectedItem) {
                    const freshSelected = items.find((item) => item.id === selectedItem.id);
                    if (freshSelected) selectItem(freshSelected);
                    else { selectedItem = null; hidden(qs('classops-selected'), true); }
                }
                text(itemState, items.length ? String(items.length) + ' مورد' : 'فهرست خالی است.');
            } catch (error) {
                text(itemState, 'خطا در دریافت فهرست: ' + (error.code || error.message));
            }
        }
        function confirmMutation(intent, summary, executor) {
            pendingMutation = {intent, executor};
            text(dialogText, summary + '\n\nexpectedRevision: ' + (intent.expectedRevision == null ? '—' : intent.expectedRevision) + '\nidempotency: ' + (intent.idempotencyKey || '—'));
            if (dialog && typeof dialog.showModal === 'function') dialog.showModal();
        }
        async function executePending() {
            if (!pendingMutation) return;
            const current = pendingMutation;
            pendingMutation = null;
            if (dialog && typeof dialog.close === 'function') dialog.close();
            try {
                await current.executor(confirmIntent(current.intent));
                await refresh();
            } catch (error) {
                if (error.status === 409 || error.code === 'CLASSOPS_REVISION_CONFLICT') {
                    await refresh();
                    hidden(conflict, false);
                    text(conflict, 'نسخه آیتم تغییر کرده؛ داده تازه دریافت شد. تغییر را دوباره بررسی کن.');
                } else {
                    text(itemState, 'عملیات انجام نشد: ' + (error.code || error.message));
                }
            }
        }

        if (dialogConfirm) dialogConfirm.addEventListener('click', executePending);
        const dialogCancel = qs('classops-confirm-no');
        if (dialogCancel) dialogCancel.addEventListener('click', () => { pendingMutation = null; if (dialog) dialog.close(); });

        const draftForm = qs('classops-draft-form');
        if (draftForm) draftForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const form = new FormData(draftForm);
            const item = {
                cohortKey:String(form.get('cohortKey') || '').trim(),
                type:String(form.get('type') || 'announcement'),
                title:String(form.get('title') || '').trim(),
                description:String(form.get('description') || '').trim(),
                importance:String(form.get('importance') || 'normal')
            };
            const intent = buildIntent('draft.create', 'owner', {payload:{type:item.type, cohortKey:item.cohortKey}, nonce:nonce()});
            confirmMutation(intent, 'پیش‌نویس ساخته شود؟ این کار پیام ارسال نمی‌کند.', (confirmed) => client.createDraft(confirmed, item, 'owner created draft from cross-surface operations center'));
        });

        const edit = qs('classops-edit');
        if (edit) edit.addEventListener('click', () => {
            if (!selectedItem) return;
            const patch = {description:String((qs('classops-edit-description') || {}).value || '')};
            const changes = diffItem(selectedItem, patch);
            text(qs('classops-diff'), changes.length ? changes.map((entry) => entry.field + ': تغییر می‌کند').join('\n') : 'تغییری وجود ندارد.');
            if (!changes.length) return;
            const intent = buildIntent('item.edit', 'owner', {itemId:selectedItem.id, expectedRevision:selectedItem.revision, payload:{changedFields:changes.map((entry) => entry.field)}, nonce:nonce()});
            confirmMutation(intent, 'ویرایش این آیتم ثبت شود؟', (confirmed) => client.update(confirmed, patch, 'owner edited item from cross-surface operations center'));
        });
        [['classops-schedule','item.schedule_intent','scheduled'],['classops-activate','item.activate_intent','active']].forEach(([id, action, status]) => {
            const button = qs(id);
            if (!button) return;
            button.addEventListener('click', () => {
                if (!selectedItem) return;
                const intent = buildIntent(action, 'owner', {itemId:selectedItem.id, expectedRevision:selectedItem.revision, payload:{status}, nonce:nonce()});
                confirmMutation(intent, (status === 'scheduled' ? 'وضعیت به scheduled تغییر کند؟' : 'وضعیت به active تغییر کند؟') + ' این تغییر مجوز ارسال پیام نیست.', (confirmed) => client.update(confirmed, {status}, 'owner lifecycle intent from cross-surface operations center'));
            });
        });
        [['classops-cancel','item.cancel'],['classops-archive','item.archive']].forEach(([id, action]) => {
            const button = qs(id);
            if (!button) return;
            button.addEventListener('click', () => {
                if (!selectedItem) return;
                const intent = buildIntent(action, 'owner', {itemId:selectedItem.id, expectedRevision:selectedItem.revision, payload:{}, nonce:nonce()});
                confirmMutation(intent, action === 'item.cancel' ? 'این آیتم لغو شود؟' : 'این آیتم آرشیو شود؟', (confirmed) => client.transition(confirmed, 'owner lifecycle transition from cross-surface operations center'));
            });
        });
        const refreshButton = qs('classops-refresh');
        if (refreshButton) refreshButton.addEventListener('click', refresh);
        const aiForm = qs('classops-ai-form');
        if (aiForm) aiForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const value = String((qs('classops-ai-text') || {}).value || '').trim();
            const intent = buildIntent('ai.draft_request', 'owner', {payload:{text:value}});
            const preview = qs('classops-ai-preview');
            text(preview, 'درخواست پیش‌نویس ساخته شد؛ هنوز به AI ارسال نشده و هیچ داده‌ای ذخیره نشده است.\n\n' + String(intent.payload.text || '').slice(0, 900));
            hidden(preview, false);
        });

        try {
            setState('loading', 'در حال بررسی دسترسی مالک...');
            await client.status();
            const capsPayload = await client.capabilities();
            const features = (capsPayload && capsPayload.features) || {};
            const runtimeCaps = Object.assign({}, FOUNDATION_CAPABILITIES, {
                'audience.resolve': !!features.audienceResolution,
                'delivery.destinations': !!features.delivery,
                'ai.provider': !!features.ai
            });
            hidden(ownerPanel, false);
            setState('ready', 'دسترسی مالک تأیید شد.');
            renderCapabilities(runtimeCaps);
            await refresh();
            return {role:'owner', capabilities:runtimeCaps};
        } catch (error) {
            hidden(ownerPanel, true);
            if (error.status === 401 || error.status === 403) setState('forbidden', 'این صفحه فقط برای مالک فعال است. کنترل‌های مدیریتی نمایش داده نمی‌شوند.');
            else setState('error', 'امکان بررسی دسترسی ClassOps وجود ندارد. هیچ عملیات مدیریتی فعال نشد.');
            return {role:'unknown', error};
        }
    }

    return mountOperationsCenter;
});
