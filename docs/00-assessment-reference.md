# Assessment requirements reference

This document summarizes the attached Purchase Management System technical assessment. Preserve these functional requirements regardless of the chosen frontend or backend organization.

## Backend
- Laravel REST API, MySQL, Laravel authentication, validation and exception handling.
- Use queues/jobs and events/listeners where appropriate.
- Keep business logic out of controllers; use transactions for critical workflows.

## Roles and authorization
- Admin: users, roles/permissions, clients, items, purchases, assignments, statuses, reports and audit logs.
- Manager: view/create/edit/assign purchases, change status, dashboard/reports and notes; cannot manage users or permissions.
- Staff: view assigned purchases, update allowed statuses, add notes and view related client/purchase information; cannot manage users, delete purchases or manage items.
- Enforce authorization in backend policies/API, not only by hiding frontend controls.

## Core modules
- Authentication: login, logout, protected API and frontend routes, current-user details, role-based access.
- Clients: list/search/filter/create/edit/view/deactivate; client detail metrics and recent purchases.
- Items: list/search/filter/create/edit, active status, price, stock and SKU.
- Purchases: CRUD; client; multiple items and quantities; priority; date; employee assignment; notes; discount, tax, subtotal and total recalculated on the backend.
- Lifecycle: Draft, Pending, Confirmed, Processing, Ready, Completed, Cancelled, with explicit valid transitions and cancellation/stock rules.
- History/audit: status history, old/new values where appropriate, user and timestamp; record purchase/client/item/assignment changes.
- Dashboard: real database statistics, status distribution and day/week/month revenue.
- Advanced purchase list: search by purchase number/client/email/item/assignee; filters, sorting and server-side pagination.
- Purchase details: summary, client, line items, timeline, activity and permission-aware actions.
- Notifications: read/unread, mark one/all read; real-time delivery is optional.
- APIs: consistent success/error envelopes and API documentation (OpenAPI or Postman).
- Seed data: at least 5 users, 100 clients, 50 items, 500+ purchases plus histories/activities/audit data.

## Quality and security
- Protect against mass assignment, SQL injection, XSS, CSRF where applicable, weak authorization and unsafe uploads.
- Use decimal-safe money calculations, indexes, eager loading, bounded pagination and concurrency-safe stock updates.
- Test authentication, purchase creation/update, status transitions, validation, authorization, client/item creation, pricing and stock.
- Provide loading, empty, error and success states on all major screens.
- Include setup, environment, migrations/seeding, API docs, architecture, trade-offs and AI usage in README/technical notes.
- This is an MVP assessment; prioritize correct, explainable core behavior over bonus features.

Source: user-provided `Technical Assessment.pdf`, pages 1–25.
