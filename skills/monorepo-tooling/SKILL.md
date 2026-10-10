# Monorepo tooling skill — vueOMS

Read `AGENTS.md` and `docs/11-monorepo-setup.md` first. Do not apply the sibling variant's architecture.

## Repository boundary
This folder is its own future project root. Do not begin work in the parent dual-pack directory or sibling variant.

## Composer and npm
Use one root Composer manifest and one root npm manifest by default. Conventional Laravel folders own backend features. Vue 3 + JavaScript is under `resources/js/`. Blade is only an optional SPA mount shell; item UI is Vue components.

## Rules
- Commit Composer/npm lockfiles.
- Never commit `.env`, secrets, `node_modules`, generated build output or credentials.
- Keep path aliases, Vite entry points, test globs and scripts consistent with the selected layout.
- Validate configuration and scripts after edits.
- Do not blindly overwrite Laravel scaffold files; merge required configuration.
- Ensure CI runs backend tests, frontend tests, type checks, lint/build and e2e as appropriate.
- Do not claim checks passed without executing them.
