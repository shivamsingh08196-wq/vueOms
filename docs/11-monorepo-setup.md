# 11 — Monorepo setup (vueOMS: Laravel 12 + Vue 3 + JavaScript)

## Pinned baseline (pre-approved)
- PHP `^8.2`; Laravel Framework `^12.0` (provider registration uses Laravel 12 `bootstrap/providers.php`)
- MySQL `8.0+`; Node.js `20.19+`
- Laravel Sanctum `^4.0`; Spatie Laravel Permission `^6.0`
- Pest `^3.8`, Pest Laravel plugin `^3.2`, PHPUnit `^11.0`, Pint `^1.24`, Larastan `^3.0`
- Vue `^3.5`, Vue Router `^4.5`, Pinia `^2.3`, TanStack Vue Query `^5.90`, VeeValidate `^4.15`, Zod `^3.25.0`
- Vite `^6.4`, Vitest `^2.1`, Vue Test Utils `^2.4`, Playwright `^1.56`, Tailwind CSS `^4.1`
- Application frontend: plain JavaScript in `.js` modules and Vue `.vue` SFCs. TypeScript is allowed only in Playwright tooling under `qa-kit/e2e/`.

These versions and conventional Laravel folder layout are pre-approved; do not ask for approval before using them.

## Install commands
Run only in the future `vueOMS` project root, never the parent pack or `VMS/`:
```bash
composer create-project laravel/laravel:^12.0 .
composer require laravel/sanctum:^4.0 spatie/laravel-permission:^6.0
composer require --dev pestphp/pest:^3.8 pestphp/pest-plugin-laravel:^3.2 phpunit/phpunit:^11.0 laravel/pint:^1.24 larastan/larastan:^3.0 --with-all-dependencies
php artisan pest:install
# QA kit remains under qa-kit/; do not overlay it onto the project root.
# Move existing Pest helpers to tests/Support/ before installing this bootstrap.
cp qa-kit/tests/Pest.php tests/Pest.php
npm install vue@^3.5 vue-router@^4.5 pinia@^2.3 @tanstack/vue-query@^5.90 vee-validate@^4.15 @vee-validate/zod@^4.15.1 zod@^3.25.0 vue-chartjs@^5 chart.js@^4
npm install -D vite@^6.4 laravel-vite-plugin@^1 @vitejs/plugin-vue@^5 vitest@^2 jsdom@^27 @vue/test-utils@^2 eslint@^9 eslint-plugin-vue@^10 vue-eslint-parser@^10 typescript@^5.9 typescript-eslint@^8 @eslint/js@^9 @types/node@^22.18 playwright@^1.56 @playwright/test@^1.56 @axe-core/playwright@^4 prettier@^3 tailwindcss@^4 @tailwindcss/vite@^4 concurrently@^9
composer dump-autoload
```

TypeScript dependencies above are for the existing Playwright configuration/specs and `typecheck:e2e` only. Do not create TypeScript application modules or `lang="ts"` blocks under `resources/js/`.

## Target layout
```text
app/                         # conventional Laravel backend
routes/api.php
routes/web.php
database/migrations/
database/seeders/
resources/views/app.blade.php # optional mount shell only
resources/js/{app.js,App.vue,router.js,stores,composables,components,features,lib,tests}
tests/                       # root Pest tests
composer.json
package.json
vite.config.ts               # tooling config
phpunit.xml
qa-kit/scripts/qa/
qa-kit/e2e/{tests,support}/    # TypeScript is allowed here for Playwright tooling
qa-kit/tests/{Feature,Unit}/
```

## Rules
1. Use one root `composer.json` and one root `package.json`.
2. Keep domain code in conventional Laravel `app/`, `routes/`, and `database/`; no Bagisto-style package-first architecture.
3. Use Vue SFCs and plain JavaScript in `resources/js/`; do not add TypeScript to application source. Item UI must not be rendered by Blade.
4. Use `resources/js/` as the only frontend source path; the static checker includes `.js` and `.vue` files.
5. Run `composer install`, `npm install`, `php artisan key:generate`, `php artisan migrate --seed`, `composer test`, `npm run typecheck`, `npm run typecheck:e2e`, `npm run test:unit`, `npm run lint`, `npm run build`, and `npm run e2e` after the application exists.
6. Root `phpunit.xml` must discover both `tests/` and any explicitly configured feature suites; unlike VMS, there are no package test roots.
7. Preserve assessment and business requirements. The user-selected JavaScript implementation choice overrides the earlier optional TypeScript preference for this variant; it does not change business rules, schema, permissions, or API contracts.

## QA-kit Pest discovery

Keep `qa-kit/` nested at the project root. Move existing shared helpers to `tests/Support/` before copying `qa-kit/tests/Pest.php` to `tests/Pest.php`. Root `phpunit.xml` must discover `tests/`, `qa-kit/tests/Feature`, and `qa-kit/tests/Unit`.
