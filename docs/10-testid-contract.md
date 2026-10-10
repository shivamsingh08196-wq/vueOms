# 10 — Frontend test contract (`data-testid`, URLs, formats)

Playwright and Vitest rely on this contract. The frontend **must** implement it; changing a name means updating the tests in the same commit. Selectors are `getByTestId` (attribute `data-testid`).

## Global conventions
- Auth persistence: Zustand `persist` key **`vms-auth`**, stored value `{"state":{"token":"…","user":{…}},"version":0}` in `localStorage`.
- Currency: USD via `Intl.NumberFormat('en-US',{style:'currency',currency:'USD'})` → `$363.00`. Counts use `toLocaleString('en-US')` → `12,345`.
- Status labels are Capitalised (`Draft, Pending, Confirmed, Processing, Ready, Completed, Cancelled`); priority labels `Low, Normal, High, Urgent`.
- Native `<select>` elements are used for: status, priority, assignee, discount type, status-change target, filters. Option `value` = enum key (`pending`, `high`, …) or user id.
- Every page renders `page-title` (h1). Toasts render `toast` with `role="status"` (success) or `role="alert"` (error).
- Default tax rate shown/used: 10%.
- Query retry: network errors retry at most once; 4xx never retried (so error states appear < 10 s).
- Generic: `field-error` on every inline validation message, `aria-invalid="true"` on the invalid control.
- Browser console must stay free of errors (Vue warnings count) during normal flows.

## Routes
`/login`, `/dashboard`, `/purchases`, `/purchases/new`, `/purchases/:id`, `/purchases/:id/edit`, `/clients`, `/clients/new`, `/clients/:id`, `/clients/:id/edit`, `/items`, `/items/new`, `/users`, `/audit-logs`, `/notifications`, `/403`.
Landing after login: Admin/Manager → `/dashboard`; Staff → `/purchases`. Unauthenticated access → `/login`. Forbidden route → renders `forbidden-page`. Unknown → `not-found-page`.

## Purchases list URL state
Query params (shareable, back-button safe): `search, status, priority, customer_id, assigned_to, date_from, date_to, sort, direction, page, per_page`.

## Test IDs

### Auth & shell
`login-email`, `login-password`, `login-submit`, `login-error`, `user-menu` (contains user name), `logout-button`, `nav-toggle` (mobile only), `nav-dashboard`, `nav-purchases`, `nav-clients`, `nav-items`, `nav-users`, `nav-audit`, `page-title`, `toast`, `forbidden-page`, `not-found-page`

### Notifications
`notification-bell`, `notification-badge` (absent when 0), `notification-item`, `notification-mark-all`

### Purchases list
`purchases-new`, `purchases-search`, `purchases-filter-status`, `purchases-filter-priority`, `purchases-filter-assignee`, `purchases-filter-from`, `purchases-filter-to`, `purchases-table`, `purchase-row` (attributes `data-purchase-number`, `data-total` numeric, `data-status`), `purchases-sort-order_date`, `purchases-sort-total`, `purchases-sort-status`, `purchases-sort-priority`, `purchases-sort-client`, `purchases-pagination`, `purchases-page-info` (text `Page 1 of 2`), `purchases-next`, `purchases-prev`, `purchases-per-page`, `purchases-skeleton`, `purchases-empty` (text "No purchases found."), `purchases-error` (text "Unable to load purchases."), `purchases-retry`

### Purchase detail
`purchase-number`, `purchase-status`, `purchase-priority`, `purchase-assignee`, `purchase-total`, `purchase-client-name`, `purchase-client-email`, `purchase-items-table`, `purchase-item-row`, `purchase-timeline`, `purchase-activity`, `purchase-detail-skeleton`, `purchase-detail-error`
Actions (rendered **only** when allowed): `action-edit`, `action-status`, `action-assign`, `action-note`, `action-cancel`, `action-delete`
Dialogs: `status-select`, `status-remarks`, `status-submit`, `assign-select`, `assign-submit`, `note-input`, `note-submit`, `cancel-reason`, `confirm-ok`, `confirm-cancel`

### Purchase edit (conflict test)
`edit-priority` (select), `edit-save`

### Wizard (create/edit)
`wizard-step-1` … `wizard-step-5` (visible only for the active step), `wizard-next`, `wizard-back`, `wizard-submit`, `wizard-client-search`, `wizard-client-option`, `wizard-item-search`, `wizard-item-option`, `wizard-qty-<index>`, `wizard-remove-<index>`, `wizard-discount-type`, `wizard-discount-value`, `wizard-priority`, `wizard-assignee`, `wizard-date`, `wizard-status`, `wizard-notes`, `wizard-preview-total`, `wizard-review-total`, `wizard-review-client`

### Dashboard
`stat-total-purchases`, `stat-pending`, `stat-confirmed`, `stat-processing`, `stat-completed`, `stat-cancelled`, `stat-revenue`, `stat-avg`, `stat-clients`, `stat-items`, `chart-status`, `chart-revenue`, `revenue-period-day`, `revenue-period-week`, `revenue-period-month`

### Clients
`clients-search`, `client-new`, `client-name`, `client-email`, `client-phone`, `client-submit`, `client-row`, `client-stat-total`, `client-stat-pending`, `client-stat-completed`, `client-stat-cancelled`, `client-stat-value`, `client-recent-purchases`

### Items
`items-search`, `item-new`, `item-name`, `item-sku`, `item-price`, `item-stock`, `item-submit`, `item-row`

## Seeded accounts (must exist after `migrate:fresh --seed`, any env)
| Role | Name | Email | Password |
|---|---|---|---|
| Admin | Admin User | admin@vms.test | password |
| Manager | Manager User | manager@vms.test | password |
| Staff | Staff User | staff@vms.test | password |
| Staff | Second Staff | staff2@vms.test | password |
| Manager | Second Manager | manager2@vms.test | password |

## Environment required for QA (monorepo root)
- `.env.e2e` (copy of `.env.e2e.example`, git-ignored): `APP_ENV=e2e`, `APP_URL=http://localhost:8001`, own database `oms_e2e`, `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, `OMS_LOGIN_THROTTLE=100000`, `OMS_API_THROTTLE=100000`. Production defaults stay `5/min` (login, keyed by email+IP) and `120/min` (API).
- One Laravel server serves **both** the API and the built SPA on `http://localhost:8001` (same origin; no CORS, no separate Vite port). Playwright's `globalSetup` runs `npm run build` and `php artisan migrate:fresh --seed`; the web server runs with `PHP_CLI_SERVER_WORKERS=4` so concurrent-request tests are real.
- Pest uses a **different** database (`oms_test` via `phpunit.xml`) so `RefreshDatabase` never wipes the e2e data.
- Unknown `/api/*` URLs must return the JSON 404 envelope (never the SPA HTML).
