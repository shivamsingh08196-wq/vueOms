# vueOMS-only correction changelog

This archive is a replacement for the `vueOMS/` folder only. The sibling `VMS/` folder is intentionally not included or modified. This is a documentation/tooling/template correction; it does not scaffold the item or implement business logic.

## Goals
1. Use plain JavaScript for Vue application source, keeping TypeScript limited to the existing Playwright tooling under `qa-kit/e2e/`.
2. Give vueOMS a clear, separate UI/UX identity and independent Vue-oriented code conventions.
3. Keep the same assessment/business requirements, schema semantics, authorization outcomes and API contracts.

## File-by-file changes

### Agent instructions and READMEs
- `.cursor/rules/oms.mdc` — synced the agent rules with the canonical JavaScript-only frontend decision and separate visual/code identity.
- `.github/copilot-instructions.md` — synced Copilot guidance with the canonical JavaScript-only frontend decision and separate visual/code identity.
- `AGENTS.md` — made plain JavaScript, Vue implementation conventions, and the pre-approved distinct UI/UX direction explicit; retained the conventional Laravel architecture.
- `CLAUDE.md` — aligned Claude instructions with AGENTS.md.
- `GEMINI.md` — aligned Gemini instructions with AGENTS.md.
- `README.md` — explained the JavaScript-only application frontend, separate UI/UX direction, and that E2E TypeScript is tooling-only.

### Architecture and implementation documentation
- `docs/02-architecture.md` — changed the selected frontend implementation from TypeScript to JavaScript, corrected frontend paths, and linked the distinct UI/UX contract. This is the one architecture document updated to reflect the user's explicit JavaScript choice; business requirements were not changed.
- `docs/06-implementation-plan.md` — replaced typed-client/typecheck wording with JSDoc, runtime validation and JavaScript syntax-check wording; clarified that the QA kit stays nested and is not overlaid.
- `docs/11-monorepo-setup.md` — documented JavaScript-only Vue source, exact app entry paths, install dependencies, and the exception for TypeScript Playwright tooling.
- `docs/12-architecture-decisions.md` — recorded JavaScript as the selected application language and the separate UI/UX identity.
- `docs/14-vueoms-item-identity.md` — new design/code contract specifying warm light surfaces, evergreen/teal actions, restrained amber accents, spacious card layouts, contextual detail drawers, responsive navigation, accessibility expectations, and independent Vue implementation patterns.
- `docs/templates/README.template.md` — aligned the reusable project README template with Vue + JavaScript.

### Monorepo template
- `monorepo-template/README.md` — documented the JavaScript-only app frontend and linked the new UI/UX contract.
- `monorepo-template/eslint.config.js` — configured Vue SFC parsing for plain JavaScript while retaining TypeScript lint support for E2E tooling.
- `monorepo-template/package.json` — removed the Vue TypeScript compiler, made the app build Vite-only, and changed `typecheck` to check JavaScript module syntax; kept TypeScript only for E2E tooling.
- `monorepo-template/resources/css/app.css` — added the concrete Tailwind 4 theme tokens and accessible focus styling for the distinct warm-light/evergreen/teal/amber visual system.
- `monorepo-template/resources/js/App.vue` — removed the TypeScript language annotation from the Vue SFC.
- `monorepo-template/resources/js/app.ts` — removed the TypeScript entry in favor of the JavaScript entry `app.js`.
- `monorepo-template/resources/js/app.js` — new JavaScript Vite entry using Vue, Pinia and Vue Router.
- `monorepo-template/resources/js/router.ts` — removed the TypeScript router in favor of `router.js`.
- `monorepo-template/resources/js/router.js` — new JavaScript router module.
- `monorepo-template/resources/js/tests/setup.ts` — replaced the TypeScript frontend test setup with `setup.js`.
- `monorepo-template/resources/js/tests/setup.js` — new JavaScript Vitest setup stub.
- `monorepo-template/resources/views/app.blade.php` — updated the optional SPA mount shell to load `resources/js/app.js`; it remains a shell, not item UI.
- `monorepo-template/tsconfig.json` — restricted TypeScript checking to Vite/Playwright tooling and excluded `resources/js/**` application source.
- `monorepo-template/vite.config.ts` — switched Vite's entry and Vitest setup to `.js` files and limited frontend unit-test globs to JavaScript specs.

### QA and skills
- `qa-kit/scripts/qa/static-checks.sh` — checks JavaScript and Vue application files for console logging, unsafe HTML and localStorage usage while still checking TypeScript E2E files for focused/skipped tests.
- `skills/deliverables/SKILL.md` — changed stack descriptions to Vue + JavaScript and includes the E2E tooling check.
- `skills/monorepo-tooling/SKILL.md` — aligned the documented frontend source language and path with the new decision.
- `skills/testing/SKILL.md` — updated the test/check workflow to distinguish JavaScript syntax checks from E2E TypeScript checks.
- `skills/vue-frontend/SKILL.md` — changed frontend guidance to Vue SFCs with plain JavaScript and documented the separate QA tooling exception.

## Intentionally unchanged
- `docs/00-assessment-reference.md`, `docs/01-requirements.md`, `docs/03-database-schema.md`, `docs/04-api-spec.md`, `docs/05-business-rules.md`, `docs/07-acceptance-checklist.md`, and `docs/10-testid-contract.md` were not edited in this correction.
- No Laravel business logic, API contract, database schema, permission matrix, or application feature was implemented.
