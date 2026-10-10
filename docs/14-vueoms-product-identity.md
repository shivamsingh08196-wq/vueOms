# vueOMS item identity: UI/UX and frontend code

This is a pre-approved design and implementation contract for the vueOMS variant. It deliberately differs from the sibling VMS React edition while preserving the same required business outcomes and API semantics.

## Visual identity
- Base surfaces: warm off-white/paper background with white content panels; avoid a dark navy app shell.
- Primary accent: evergreen/teal for primary actions and active navigation. Use amber sparingly for attention/status. Keep semantic success, warning, danger, and neutral states accessible and consistent.
- Layout: spacious dashboard cards and section groupings, readable headings, visible whitespace, and responsive single-column collapse on narrow screens.
- Navigation: compact top header plus a collapsible left rail on desktop; mobile navigation becomes a clear drawer or bottom action/navigation pattern. Do not copy the sibling's navigation geometry.
- Lists: default to readable rows with clear column hierarchy, filters in a dedicated toolbar, and contextual detail drawers. Dense data tables remain available where operational work requires them, but should not define every screen.
- Forms: progressive disclosure for long purchase workflows, clear step labels, inline validation, persistent save/cancel affordances, and unsaved-change feedback where applicable.
- Feedback: unobtrusive toast messages, explicit empty/loading/error/retry states, and accessible focus indicators. Never communicate permission solely by hiding a control; backend authorization remains authoritative.
- Charts: use Vue-compatible chart tooling; label values and provide non-color cues for status.
- Accessibility: semantic HTML, keyboard operability, visible focus, adequate contrast, form labels, reduced-motion support, and axe/Playwright coverage.

## Frontend implementation identity
- Application source lives only in `resources/js/` and uses `.js` and `.vue`; no `.ts`, `.tsx`, `lang="ts"`, `vue-tsc`, or TypeScript application modules.
- Vue SFCs use `<script setup>` with plain JavaScript. Use Composition API and extract reusable behavior into named composables (`useOrders.js`, `useOrderFilters.js`, etc.).
- Organize by feature (`features/purchases/`, `features/clients/`, `features/items/`) with colocated page components, smaller components, API modules, and tests. Keep shared primitives in `components/ui/` and shell/navigation in `components/layout/`.
- Use Vue Router for routes; Pinia for cross-screen client state only; TanStack Vue Query for API/server state, caching and invalidation.
- Use VeeValidate + Zod for form state and runtime schemas. Validate responses at important API boundaries with Zod where useful. Use JSDoc for public functions, complex data shapes and service contracts rather than converting the app to TypeScript.
- Keep HTTP access in feature API modules or one shared client; do not issue fetch/axios calls directly throughout templates.
- Use CSS variables/Tailwind tokens for this variant's design system. Do not copy sibling VMS design tokens, component files, class strings, or page markup.
- Playwright configuration and E2E test files under `qa-kit/e2e/` may remain TypeScript as tooling; this exception does not extend to `resources/js/**`.

## What must remain consistent
- Assessment requirements, business rules, database schema semantics, authorization outcomes and API contracts stay governed by docs/00–05.
- Different UI and implementation are not permission to invent different business behavior or alter data just to make the projects look unrelated.
- Implement the same outcomes independently using Vue patterns; do not port React components or copy the sibling's UI source.

## Acceptance criteria
- `resources/js/` contains no `.ts` or `.tsx` application source and no `lang="ts"` blocks.
- The interface uses the warm light / evergreen-teal / amber visual system and spacious, contextual page composition above.
- Core screens expose loading, empty, error/retry, success, validation and permission-aware states.
- Mobile/tablet/desktop layouts, keyboard navigation, focus visibility and automated accessibility checks are covered.
- Existing API contracts and business outcomes remain unchanged.
