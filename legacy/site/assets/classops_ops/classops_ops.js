(function (root, factory) {
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    if (root) root.ClassOpsOps = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const CONTRACT_VERSION = 'classops-surface-v1';
    const ACTIONS = Object.freeze({
        'items.list': {roles:['owner'], kind:'read', capability:'foundation.read', confirmation:false, revision:false, idempotency:false, label:'فهرست عملیات'},
        'item.get': {roles:['owner'], kind:'read', capability:'foundation.read', confirmation:false, revision:false, idempotency:false, label:'مشاهده جزئیات'},
        'draft.create': {roles:['owner'], kind:'mutation', capability:'foundation.create', confirmation:true, revision:false, idempotency:true, label:'ساخت پیش‌نویس'},
        'item.preview_diff': {roles:['owner'], kind:'preview', capability:'surface.preview', confirmation:false, revision:false, idempotency:false, label:'پیش‌نمایش تغییرات'},
        'item.edit': {roles:['owner'], kind:'mutation', capability:'foundation.update', confirmation:true, revision:true, idempotency:true, label:'ویرایش'},
        'item.schedule_intent': {roles:['owner'], kind:'mutation', capability:'foundation.update', confirmation:true, revision:true, idempotency:true, label:'زمان‌بندی وضعیت'},
        'item.activate_intent': {roles:['owner'], kind:'mutation', capability:'foundation.update', confirmation:true, revision:true, idempotency:true, label:'فعال‌سازی وضعیت'},
        'item.cancel': {roles:['owner'], kind:'mutation', capability:'foundation.cancel', confirmation:true, revision:true, idempotency:true, label:'لغو'},
        'item.archive': {roles:['owner'], kind:'mutation', capability:'foundation.archive', confirmation:true, revision:true, idempotency:true, label:'آرشیو'},
        'audience.preview': {roles:['owner'], kind:'preview', capability:'audience.resolve', confirmation:false, revision:false, idempotency:false, label:'پیش‌نمایش مخاطب'},
        'destination.preview': {roles:['owner'], kind:'preview', capability:'delivery.destinations', confirmation:false, revision:false, idempotency:false, label:'پیش‌نمایش مقصد'},
        'task.requirement_view': {roles:['owner','student'], kind:'read', capability:'task.requirement', confirmation:false, revision:false, idempotency:false, label:'تکلیف و الزام‌ها'},
        'exam.view': {roles:['owner','student'], kind:'read', capability:'exam.view', confirmation:false, revision:false, idempotency:false, label:'آزمون‌ها'},
        'exam.ack': {roles:['student'], kind:'mutation', capability:'exam.critical_ack', confirmation:true, revision:true, idempotency:true, label:'تأیید اطلاعیه حیاتی'},
        'reminder.preview': {roles:['owner','student'], kind:'preview', capability:'reminder.preview', confirmation:false, revision:false, idempotency:false, label:'پیش‌نمایش یادآوری'},
        'summary.tomorrow': {roles:['owner','student'], kind:'read', capability:'summary.tomorrow', confirmation:false, revision:false, idempotency:false, label:'فردا'},
        'summary.weekly': {roles:['owner','student'], kind:'read', capability:'summary.weekly', confirmation:false, revision:false, idempotency:false, label:'هفته پیش‌رو'},
        'ai.draft_request': {roles:['owner'], kind:'preview', capability:'ai.preview_request', confirmation:false, revision:false, idempotency:false, label:'درخواست پیش‌نویس با متن آزاد'}
    });

    const FOUNDATION_CAPABILITIES = Object.freeze({
        'foundation.read': true, 'foundation.create': true, 'foundation.update': true,
        'foundation.cancel': true, 'foundation.archive': true, 'surface.preview': true,
        'ai.preview_request': true, 'audience.resolve': false, 'delivery.destinations': false,
        'task.requirement': false, 'exam.view': false, 'exam.critical_ack': false,
        'reminder.preview': false, 'summary.tomorrow': false, 'summary.weekly': false
    });

    const FORBIDDEN_KEY = /(chat[_-]?id|telegram[_-]?id|bale[_-]?id|bot[_-]?token|token|secret|password|national[_-]?code|phone|mobile|otp)/i;

    function safePayload(value) {
        if (!value || typeof value !== 'object') return true;
        if (Array.isArray(value)) return value.every(safePayload);
        return Object.entries(value).every(([key, child]) => !FORBIDDEN_KEY.test(key) && safePayload(child));
    }

    function hash32(input) {
        let h = 0x811c9dc5;
        for (let i = 0; i < input.length; i += 1) {
            h ^= input.charCodeAt(i);
            h = Math.imul(h, 0x01000193) >>> 0;
        }
        return h.toString(16).padStart(8, '0');
    }

    function makeIdempotencyKey(action, itemId, revision, nonce) {
        const seed = [CONTRACT_VERSION, action, itemId || '-', revision || 0, String(nonce || '')].join('|');
        if (String(nonce || '').length < 8) throw new Error('intent nonce is too short');
        return 'surface_' + hash32(seed + '|a') + hash32(seed + '|b') + hash32(seed + '|c') + hash32(seed + '|d');
    }

    function buildIntent(action, role, context) {
        const spec = ACTIONS[action];
        const ctx = context || {};
        if (!spec) throw new Error('unknown ClassOps surface action');
        if (!spec.roles.includes(role)) throw new Error('action is not allowed for this role');
        if (!safePayload(ctx.payload || {})) throw new Error('forbidden identifier or secret field');
        let revision = null;
        if (spec.revision) {
            revision = Number(ctx.expectedRevision || 0);
            if (!Number.isInteger(revision) || revision < 1) throw new Error('expectedRevision is required');
        }
        const itemId = ctx.itemId == null ? null : String(ctx.itemId).trim();
        const idempotencyKey = spec.idempotency
            ? makeIdempotencyKey(action, itemId, revision, ctx.nonce || 'browser-intent-nonce')
            : null;
        return Object.freeze({
            contractVersion: CONTRACT_VERSION,
            action,
            actorRole: role,
            itemId,
            expectedRevision: revision,
            idempotencyKey,
            confirmationRequired: !!spec.confirmation,
            confirmed: false,
            payload: Object.freeze(Object.assign({}, ctx.payload || {}))
        });
    }

    function confirmIntent(intent) {
        if (!intent.confirmationRequired) return intent;
        return Object.freeze(Object.assign({}, intent, {confirmed:true}));
    }

    function diffItem(current, patch) {
        const result = [];
        Object.keys(patch || {}).sort().forEach((key) => {
            const before = current ? current[key] : undefined;
            const after = patch[key];
            if (JSON.stringify(before) !== JSON.stringify(after)) result.push({field:key, before, after});
        });
        return result;
    }

    function capabilityModel(role, capabilities) {
        const caps = Object.assign({}, FOUNDATION_CAPABILITIES, capabilities || {});
        return Object.entries(ACTIONS)
            .filter(([, spec]) => spec.roles.includes(role))
            .map(([action, spec]) => ({
                action,
                label: spec.label,
                enabled: !!caps[spec.capability],
                capability: spec.capability,
                confirmationRequired: !!spec.confirmation,
                disabledReason: caps[spec.capability] ? '' : 'backend-integration-pending'
            }));
    }

    function studentViewModel(capabilities, authorizedActions) {
        const allowed = new Set(authorizedActions || []);
        return capabilityModel('student', capabilities).map((entry) => Object.assign({}, entry, {
            enabled: entry.enabled && allowed.has(entry.action),
            disabledReason: entry.enabled && !allowed.has(entry.action) ? 'not-authorized' : entry.disabledReason
        }));
    }

    class ClassOpsClient {
        constructor(options) {
            const opts = options || {};
            this.endpoint = opts.endpoint || '/api/classops_api.php';
            this.fetchImpl = opts.fetchImpl || (typeof fetch === 'function' ? fetch.bind(globalThis) : null);
            this.csrfProvider = opts.csrfProvider || null;
            this.authSessionsEndpoint = opts.authSessionsEndpoint || '/api/auth_api.php?action=authSessions';
            this.csrfToken = '';
            if (!this.fetchImpl) throw new Error('fetch implementation is required');
        }
        async ensureCsrf() {
            if (this.csrfToken) return this.csrfToken;
            if (this.csrfProvider) {
                const supplied = await this.csrfProvider();
                if (supplied) { this.csrfToken = String(supplied); return this.csrfToken; }
            }
            const response = await this.fetchImpl(this.authSessionsEndpoint, {method:'GET', credentials:'same-origin', cache:'no-store', headers:{'Accept':'application/json'}});
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || payload.success === false || !payload.csrfToken) throw new Error('CSRF token is unavailable');
            this.csrfToken = String(payload.csrfToken);
            return this.csrfToken;
        }
        async request(action, options) {
            const opts = options || {};
            const method = opts.method || 'GET';
            const url = new URL(this.endpoint, typeof location !== 'undefined' ? location.origin : 'https://example.invalid');
            url.searchParams.set('action', action);
            Object.entries(opts.query || {}).forEach(([key, value]) => {
                if (value !== '' && value != null) url.searchParams.set(key, String(value));
            });
            const headers = {'Accept':'application/json'};
            const init = {method, headers, credentials:'same-origin'};
            if (method === 'POST') {
                headers['Content-Type'] = 'application/json';
                const csrf = await this.ensureCsrf();
                headers['X-CSRF-Token'] = csrf;
                init.body = JSON.stringify(Object.assign({action}, opts.body || {}));
            }
            const response = await this.fetchImpl(url.toString(), init);
            let payload = {};
            try { payload = await response.json(); } catch (_) { payload = {}; }
            if (!response.ok || payload.success === false) {
                const error = new Error(payload.error || payload.message || 'ClassOps request failed');
                error.status = response.status;
                error.code = payload.code || (payload.details && payload.details.code) || '';
                throw error;
            }
            return payload;
        }
        capabilities() { return this.request('capabilities'); }
        status() { return this.request('status'); }
        list(filters) { return this.request('list', {query:filters || {}}); }
        get(id) { return this.request('get', {query:{id}}); }
        createDraft(intent, item, reason) {
            if (!intent.confirmed || intent.action !== 'draft.create') throw new Error('confirmed draft.create intent required');
            const safe = Object.assign({}, item || {}, {status:'draft'});
            return this.request('create', {method:'POST', body:{idempotencyKey:intent.idempotencyKey, reason:reason || 'owner operations center', item:safe}});
        }
        update(intent, patch, reason) {
            if (!intent.confirmed || !['item.edit','item.schedule_intent','item.activate_intent'].includes(intent.action)) throw new Error('confirmed update intent required');
            return this.request('update', {method:'POST', body:{idempotencyKey:intent.idempotencyKey, reason:reason || 'owner operations center', id:intent.itemId, expectedRevision:intent.expectedRevision, patch:patch || {}}});
        }
        transition(intent, reason) {
            if (!intent.confirmed || !['item.cancel','item.archive'].includes(intent.action)) throw new Error('confirmed transition intent required');
            return this.request(intent.action === 'item.cancel' ? 'cancel' : 'archive', {method:'POST', body:{idempotencyKey:intent.idempotencyKey, reason:reason || 'owner operations center', id:intent.itemId, expectedRevision:intent.expectedRevision}});
        }
    }

    return Object.freeze({
        CONTRACT_VERSION, ACTIONS, FOUNDATION_CAPABILITIES, ClassOpsClient,
        buildIntent, confirmIntent, makeIdempotencyKey, diffItem, capabilityModel,
        studentViewModel, safePayload
    });
});
