import { assertRuntimeContext } from './runtime-contract.js';

export const MODULE_CONTRACT_VERSION = 'FANOOS-V3-MODULE-1';

export const MODULE_SHAPE_KEYS = Object.freeze([
  'id',
  'routes',
  'navItems',
  'mount',
  'unmount',
]);

export const MODULE_LIFECYCLE_EXPECTATIONS = Object.freeze({
  mount: 'Render only from canonical ctx inputs, bind work to ctx.signal, and return only after initial synchronous setup is safe.',
  unmount: 'Release presentation-only references/overlays and remain safe when ctx.signal is already aborted.',
  cleanup: 'The shell aborts the mount-scoped AbortSignal so listeners, requests and timers cannot update a later route/workspace.',
  authority: 'A module must not create a second backend, router, durable store, authorization decision, scoring decision, payment truth or entitlement truth.',
});

function assertId(id) {
  if (typeof id !== 'string' || !/^[a-z][a-z0-9-]*$/.test(id)) {
    throw new TypeError('Module id must be a lowercase kebab-case identifier');
  }
}

function assertArray(name, value) {
  if (!Array.isArray(value)) throw new TypeError(`Module ${name} must be an array`);
}

export function assertModuleDefinition(moduleDefinition) {
  if (!moduleDefinition || typeof moduleDefinition !== 'object') throw new TypeError('Module definition is required');
  assertId(moduleDefinition.id);
  assertArray('routes', moduleDefinition.routes);
  assertArray('navItems', moduleDefinition.navItems);
  if (typeof moduleDefinition.mount !== 'function') throw new TypeError('Module mount(ctx) is required');
  if (typeof moduleDefinition.unmount !== 'function') throw new TypeError('Module unmount(ctx) is required');
  return moduleDefinition;
}

export function defineModule(moduleDefinition) {
  assertModuleDefinition(moduleDefinition);
  const normalized = {
    ...moduleDefinition,
    routes: Object.freeze([...moduleDefinition.routes]),
    navItems: Object.freeze([...moduleDefinition.navItems]),
  };
  return Object.freeze(normalized);
}

export async function mountModule(moduleDefinition, ctx) {
  const module = assertModuleDefinition(moduleDefinition);
  assertRuntimeContext(ctx);
  if (ctx.signal.aborted) return undefined;
  return module.mount(ctx);
}

export async function unmountModule(moduleDefinition, ctx) {
  const module = assertModuleDefinition(moduleDefinition);
  assertRuntimeContext(ctx);
  return module.unmount(ctx);
}
