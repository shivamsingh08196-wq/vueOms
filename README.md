# Monorepo template notes — vueOMS

Canonical instructions: `AGENTS.md`, `docs/02-architecture.md`, and `docs/11-monorepo-setup.md`.

This is a reference tooling template, not a completed application. Backend features use conventional Laravel `app/`, `routes/`, and `database/` directories. The Vue 3 + plain JavaScript UI lives under `resources/js/`, with `resources/js/app.js` as the Vite entry. Application source must not use TypeScript; only Playwright tooling under `qa-kit/e2e/` may remain TypeScript. Vite alias `@` resolves to `resources/js/`. Follow `docs/14-vueoms-item-identity.md` for a distinct warm-light/evergreen visual system and independent Vue implementation. Use Vue SFCs for item UI; Blade is only an optional minimal SPA mount shell. Do not add Bagisto-style package-first backend structure.

## QA kit location

Keep `qa-kit/` at the project root; do not copy or overlay its contents. Run checks from the project root, for example `bash qa-kit/scripts/qa/run-all.sh` or `make -f qa-kit/Makefile qa`. Before using the kit Pest bootstrap, move existing shared helpers to `tests/Support/`, then copy it with `cp qa-kit/tests/Pest.php tests/Pest.php`.
