# 12 — Architecture decisions (ADR summary)

## Chosen variant: vueOMS

| Decision | vueOMS choice |
|---|---|
| Backend organization | Conventional Laravel app structure |
| Frontend | Vue 3 + JavaScript |
| UI implementation | Vue components; Blade only as optional mount shell |
| Visual identity | Warm light surfaces, evergreen/teal actions, amber accents, spacious cards and responsive task-oriented navigation |
| Frontend coding style | Vue Composition API, feature-first folders, composables, JSDoc and Zod runtime validation; no TypeScript in `resources/js/**` |
| Application language | Plain JavaScript for Vue app source; TypeScript only for existing Playwright tooling under `qa-kit/e2e/` |
| Distinct UI/UX | Warm light surfaces, evergreen/teal primary actions, restrained amber accents, spacious cards and contextual detail drawers |
| Repository | One Git repository for this variant; separate from sibling variant |
| Package manifests | Root Composer/npm manifests by default |
| Tests | Pest/PHPUnit, frontend unit/component tests, Playwright for key flows |

## Non-goals
- Do not merge `vueOMS` and `VMS` into one runtime project.
- Do not start development in the parent pack directory.
- Do not silently switch this variant to the sibling's architecture.
- Do not claim that package conformance, tests, or builds are verified unless they have actually been inspected/run.

## Reference links
- Laravel packages: https://laravel.com/docs/packages
- Bagisto repository (relevant to VMS only): https://github.com/bagisto/bagisto
- Laravel documentation: https://laravel.com/docs
