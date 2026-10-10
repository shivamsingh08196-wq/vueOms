# Laravel backend skill — vueOMS

Read `AGENTS.md` and `docs/02-architecture.md` first. These are authoritative.

## Common requirements
- Use the Laravel version selected by the project; verify version-specific APIs before relying on them.
- Thin controllers, Form Requests, API Resources, Policies/Gates, safe validation, consistent errors and meaningful tests.
- Use database transactions for critical multi-write operations.
- Recalculate pricing server-side; use decimal-safe money handling.
- Protect stock updates against concurrency with appropriate transactions and row locks.
- Keep status transition rules centralized and tested.
- Record audit/history data consistently with the state change.
- Use events/jobs/queues only where appropriate; dispatch post-commit side effects safely.
- Do not claim tests passed unless they were run.

## Project organization
Use conventional Laravel application locations: `app/Models`, `app/Http`, `app/Services` where useful, `routes/`, and `database/migrations/`. Do not use `packages/VMS/*` as the primary feature organization in vueOMS.

## Authorization
The backend is the source of truth. Check policy authorization for every sensitive operation. Frontend button visibility is not access control.
