# Vue frontend skill — vueOMS

Use Vue 3 + JavaScript. Build UI as `.vue` single-file components with `<script setup>` and plain JavaScript; keep all item UI out of Blade.

- Source root: `resources/js/`.
- Routing: Vue Router 4.
- Shared client state: Pinia 2 for cross-screen state; avoid duplicating API server state.
- Server state/cache: TanStack Vue Query 5.
- Forms: VeeValidate + Zod; validate server-side as well.
- Unit/component tests: Vitest + `@vue/test-utils` with jsdom; browser flows with Playwright and `@axe-core/playwright`.
- Use an API client documented with JSDoc and runtime response validation, loading/empty/error/success states, accessible labels, and permission-aware controls. The server remains the authorization boundary.
- Use `v-html` only with a compelling documented and sanitized requirement; prefer interpolation. Do not use the other variant’s UI framework or files in this project.
- Run `npm run typecheck` (JavaScript syntax check), `npm run typecheck:e2e` (Playwright tooling only), `npm run test:unit`, `npm run lint`, `npm run build`, and `npm run e2e` when the application exists. Do not report unrun commands as passing.
