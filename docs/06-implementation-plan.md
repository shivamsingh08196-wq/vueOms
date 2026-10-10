# 06 — Implementation Plan (5 days, MVP-first) — vueOMS

Rule: complete a phase's exit criteria and commit before moving on. Read `AGENTS.md`, `docs/02-architecture.md` and `docs/11-monorepo-setup.md` first. Do not start in the parent dual-pack directory or sibling variant.

## Phase 0 — Setup (½ day)
**Skills:** `monorepo-tooling`, `laravel-backend`, `vue-frontend` **Docs:** 02, 11
- Scaffold Laravel only inside this variant's project root.
- Use conventional Laravel app structure (`app/`, `routes/`, `database/`).
- Configure Vue 3 + JavaScript, Vite, frontend router/query/state tooling where justified, plus backend auth and permissions.
- Configure MySQL `.env.example`, VMS configuration, consistent API response/error handling, public `GET /api/health`, and JSON 404s for unknown API routes.
- Merge the relevant tooling template carefully; do not overwrite framework files blindly.
**Exit:** Laravel starts, `GET /api/health` returns the documented envelope, frontend production build and JavaScript syntax checks pass. Commit the setup.

## Phase 1 — Schema, models and seeders (½ day)
**Skills:** `laravel-backend`, `db-performance` **Docs:** 03
- Create tables, indexes and foreign keys; define enums and relationships.
- Keep models/migrations in conventional Laravel directories.
- Seed roles/permissions, 5 users, 100 clients, 50 items, 500+ purchases with consistent history, notes, activities and audit records.
**Exit:** migrations/seeding succeed; counts match; representative listing query uses appropriate indexes.

## Phase 2 — Authentication and RBAC (½ day)
**Skills:** `laravel-backend`, `rbac-policies`, `testing` **Docs:** 04
- Login/logout/current user, throttling, inactive-user block, role/permission seed data, policies and Staff scoping.
- Test login success/failure, protected route 401 and role 403 matrix.
**Exit:** authentication and authorization tests pass.

## Phase 3 — Clients and Items (½ day)
**Skills:** `laravel-backend`, `audit-logging`, `testing`
- CRUD, search/filter/pagination, deactivate/soft-delete policy, client stats, stock adjustments, lookup endpoints and audit events.
**Exit:** client/item validation, authorization and audit tests pass.

## Phase 4 — Purchases core (1 day)
**Skills:** `purchase-workflow`, `laravel-backend`, `audit-logging`, `db-performance`, `testing` **Docs:** 04, 05
- Implement pricing, create/update/delete, status transition rules, stock service, query filters, assignment, notes, activity, history, events/listeners and notification endpoints.
- Use transactions, row locks where needed and optimistic conflict handling where appropriate.
**Exit:** tests cover pricing example, CRUD, validation, valid/invalid transitions, stock reserve/restore, insufficient stock, Staff scoping and conflicts.

## Phase 5 — Dashboard and audit APIs (¼ day)
**Skills:** `db-performance`
- Real database summary, status distribution, revenue trends by day/week/month, caching only when justified, and audit listing.
**Exit:** aggregates match fixture data.

## Phase 6 — Frontend foundation (¾ day)
**Skills:** `vue-frontend`
- Build the API client with JSDoc documentation and runtime response validation, authentication UX, router guards, AppShell, reusable UI kit, toast/notification handling, server-state defaults and API validation-error mapping.
- All item UI must be implemented with Vue components, not Blade UI components. Use plain JavaScript for application source.
- Frontend location: `resources/js/`.
**Exit:** login and protected dashboard shell work; API 401 is handled; shared UI components are reused.

## Phase 7 — Frontend features (1¼ days)
**Skills:** `vue-frontend`, `purchase-workflow`
Implement in purchase: purchase list → purchase detail/timeline/activity → create/edit wizard → clients → items → dashboard/charts → users/roles → audit log → notifications.
Every screen supports loading, empty, error/retry and permission-aware states. Test Admin, Manager and Staff at mobile/tablet/desktop widths.
**Exit:** critical workflows complete for all roles.

## Phase 8 — Tests, docs and polish (½ day)
**Skills:** `testing`, `deliverables`
- Frontend tests with Vitest + Vue Test Utils; Playwright for key user journeys.
- Add Postman/OpenAPI, README, TECHNICAL_NOTES and AI usage notes; run formatter, lint, static analysis, JavaScript syntax checks and production build.
- Keep `qa-kit/` nested at the project root; never overlay its contents onto the project root. Move existing Pest helpers to `tests/Support/` before copying `qa-kit/tests/Pest.php` to `tests/Pest.php`. For VueOMS, confirm frontend test paths and dependencies match the selected frontend.
- Run the documented QA loop until consecutive verification runs are clean; report actual command outputs.
**Exit:** acceptance checklist is complete and QA report is committed.

## Bonus (only if time)
Low-stock notifications, CSV export, purchase duplication, Docker, Redis caching, Kanban, PDF invoice and broadcasting—in that purchase.

## Time-box guidance
Cut bonus work first, then non-critical UI polish and advanced charting. Never cut transactions, server-side totals, authorization policies, status transition rules, core tests or documentation.
