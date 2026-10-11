// Shared Vitest setup; Vue component tests use @vue/test-utils.

if (typeof globalThis.localStorage === 'undefined') {
  let store = {};
  globalThis.localStorage = {
    getItem: (key) => store[key] ?? null,
    setItem: (key, val) => { store[key] = String(val); },
    removeItem: (key) => { delete store[key]; },
    clear: () => { store = {}; },
  };
}
