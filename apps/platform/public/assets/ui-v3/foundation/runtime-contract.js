export const RUNTIME_CONTRACT_VERSION = 'FANOOS-V3-RUNTIME-1';

export const RUNTIME_CONTEXT_KEYS = Object.freeze([
  'root',
  'api',
  'state',
  'navigate',
  'format',
  'ui',
  'capabilities',
  'signal',
]);

export const FORMATTER_KEYS = Object.freeze([
  'text',
  'number',
  'date',
  'dateTime',
  'time',
  'money',
  'status',
  'resourceType',
  'assessmentType',
]);

/**
 * Merge-time V3 runtime context.
 *
 * root:
 *   Element owned by the currently mounted module. A module renders only inside
 *   this root unless it creates a foundation overlay that is still appended
 *   inside the V3 application root. The shell owns surrounding application chrome.
 *
 * api:
 *   Canonical browser API adapter supplied by the integrated application.
 *   Required call shape: api(path, options?) -> Promise<data>. It preserves the
 *   existing bearer-session, CSRF, workspace authorization, stale-response and
 *   session-expiry semantics. Foundation/feature modules never create a second
 *   HTTP client or backend authority.
 *
 * state:
 *   Presentation state supplied and owned by the integrated app. It is not a
 *   durable domain store. Canonical workspace, identity, payment, entitlement,
 *   grade/scoring and permission facts remain server-owned.
 *
 * navigate:
 *   Application-owned navigation function. Modules request navigation through
 *   it rather than mutating a second router/history authority.
 *
 * format:
 *   Application adapters named by FORMATTER_KEYS. They preserve the current
 *   Persian/text, canonical workspace-timezone, money and status semantics.
 *   Feature modules do not reproduce domain formatting rules locally.
 *
 * ui:
 *   Foundation UI namespace/factories (normally the exports from ./ui.js).
 *
 * capabilities:
 *   Presentation capability projection with has(name) -> boolean. It controls
 *   affordance visibility only and never replaces backend authorization.
 *
 * signal:
 *   AbortSignal scoped to one module mount. Every asynchronous read, listener,
 *   timer or controller that can outlive the current mount must be tied to it.
 *   The integration shell aborts it before/while unmounting the module.
 */

export function isAbortSignal(value) {
  return Boolean(
    value
    && typeof value === 'object'
    && typeof value.aborted === 'boolean'
    && typeof value.addEventListener === 'function',
  );
}

export function assertRuntimeContext(ctx) {
  if (!ctx || typeof ctx !== 'object') throw new TypeError('V3 runtime context is required');
  if (!ctx.root || ctx.root.nodeType !== 1) throw new TypeError('ctx.root must be an Element');
  if (typeof ctx.api !== 'function') throw new TypeError('ctx.api must be the application API adapter');
  if (!ctx.state || typeof ctx.state !== 'object') throw new TypeError('ctx.state must be the application presentation state');
  if (typeof ctx.navigate !== 'function') throw new TypeError('ctx.navigate must be a function');
  if (!ctx.format || typeof ctx.format !== 'object') throw new TypeError('ctx.format must be an object');
  for (const key of FORMATTER_KEYS) {
    if (typeof ctx.format[key] !== 'function') throw new TypeError(`ctx.format.${key} must be a function`);
  }
  if (!ctx.ui || typeof ctx.ui !== 'object') throw new TypeError('ctx.ui must be an object');
  if (!ctx.capabilities || typeof ctx.capabilities.has !== 'function') throw new TypeError('ctx.capabilities.has must be a function');
  if (!isAbortSignal(ctx.signal)) throw new TypeError('ctx.signal must be an AbortSignal');
  return ctx;
}
