# 02 — Architecture (vueOMS)

## Decision summary
- Conventional Laravel application, not Bagisto-style package-first.
- Laravel REST API + MySQL.
- Vue 3 SPA using Vue Single-File Components and plain JavaScript (the selected implementation uses JavaScript, not TypeScript).
- UI is implemented with Vue components. Blade is not used for item screens or reusable UI; at most one minimal Blade mount shell serves the SPA.
- One project root, one Composer manifest, one npm manifest by default.

## Runtime flow
Browser → minimal Laravel SPA mount shell (optional) → Vue Router → Vue components → JavaScript API client with JSDoc and runtime validation → Laravel REST API → Form Request → Policy → thin Controller → service/domain operation → transaction/database → events/jobs as appropriate.

## Backend layout
```text
app/
  Enums/
  Exceptions/
  Http/Controllers/Api/
  Http/Requests/
  Http/Resources/
  Models/
  Policies/
  Services/
  Events/
  Listeners/
  Jobs/
routes/api.php
routes/web.php                 # SPA fallback only if SPA is served by Laravel
database/migrations/
database/seeders/
tests/Feature/
tests/Unit/
```
Keep conventional framework locations. Do not create `packages/VMS/*` as the primary architecture in this variant.

## Frontend layout
```text
resources/js/
  app.js
  api/                          # documented JavaScript API client and domain API functions
  components/
    ui/                          # Button, Input, Modal, Table, Badge, etc.
    layout/
  features/
    auth/
    dashboard/
    purchases/
    clients/
    items/
    users/
    audit/
    notifications/
  router.js
  stores/                        # Pinia only for shared client state
  composables/
  lib/
  schemas/                     # Zod runtime schemas and API contracts
  tests/
resources/css/
resources/views/app.blade.php   # optional mount shell only; no item UI
```

## UI/UX identity
Follow `docs/14-vueoms-item-identity.md`: warm light surfaces, evergreen/teal primary actions, restrained amber accents, spacious cards, contextual detail drawers, and a mobile-first navigation system. Do not copy the sibling VMS React UI, design tokens, component implementations, or page composition.

## Key decisions
| Concern | Decision | Reason |
|---|---|---|
| Backend organization | Conventional Laravel `app/`, `routes/`, `database/` | This variant intentionally does not follow Bagisto's package architecture |
| Frontend | Vue 3 + JavaScript + Vite | Component-driven SPA with JSDoc contracts and runtime validation |
| UI rendering | Vue components | No Blade components/templates for item screens |
| API | RESTful JSON API | Clear backend/frontend boundary |
| API data fetching | TanStack Vue Query or documented alternative | Cache, invalidation, loading/error states |
| Shared client state | Pinia only where needed | Avoid duplicating server state |
| Validation | Vue-side feedback + Laravel Form Requests | Backend remains authoritative |
| Authorization | Laravel Policies/Gates and role permissions | UI visibility alone is not security |
| Money | DECIMAL database columns and decimal-safe calculations | Avoid floating point rounding |
| Lifecycle | One status enum/transition service | Prevent invalid transitions |
| Inventory | Lock relevant item rows and update stock transactionally | Avoid overselling |
| Audit | Record critical changes in the same transaction where appropriate | Keep audit trail consistent |
| Testing | Pest/PHPUnit + Vitest/Vue Test Utils + Playwright | Test API, components and user flows |

## Request flow example
1. `POST /api/purchases/{id}/status` authenticates the user.
2. Form Request validates the request and authorization.
3. Policy verifies role, assignment and permitted action.
4. Controller calls a focused service.
5. A transaction locks the purchase/item rows as needed, checks transition/stock rules, updates purchase and inventory, writes status history and audit/activity records.
6. Events/queued listeners handle post-commit notifications where appropriate.
7. API Resource returns a consistent response.

## Scale and security
Use indexed filters, eager loading, bounded server-side pagination, decimal-safe money calculations, API rate limits, safe validation, authorization, mass-assignment protection, secure auth/session handling, and transaction/locking for concurrent inventory changes. Design for the assessment's large data volumes without prematurely adding distributed infrastructure.
