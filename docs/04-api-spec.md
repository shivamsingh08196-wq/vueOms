# 04 — API Specification

Base: `/api`. JSON only. Auth: `Authorization: Bearer <token>` (Sanctum) on everything except `POST /login`.

## Envelope

Success:
```json
{ "success": true, "message": "Purchase created successfully", "data": { } }
```
Paginated success adds `"meta": {"current_page":1,"per_page":15,"total":532,"last_page":36}` and `"links"`.

Error:
```json
{ "success": false, "message": "The given data was invalid.", "errors": { "items.0.quantity": ["Quantity must be greater than 0."] }, "code": "VALIDATION_ERROR" }
```

| HTTP | code | When |
|---|---|---|
| 401 | UNAUTHENTICATED | missing/invalid token |
| 401 | INVALID_CREDENTIALS | wrong email/password or inactive user at login (same message for both) |
| 403 | FORBIDDEN | policy/permission denied |
| 404 | NOT_FOUND | model not found |
| 409 | CONFLICT | stale version / concurrent edit |
| 422 | VALIDATION_ERROR / INVALID_STATUS_TRANSITION / INSUFFICIENT_STOCK | business or validation failure |
| 429 | TOO_MANY_REQUESTS | throttled (login 5/min, API 120/min) |
| 500 | SERVER_ERROR | generic message only; details to logs |

Implement centrally in `bootstrap/app.php` exception renderer + a `ApiResponse` helper/trait.

## Auth

| Method | Path | Body | Notes |
|---|---|---|---|
| POST | /login | `email, password, device_name?` | returns `{token, user{id,name,email,role,permissions[]}}`; blocks inactive users; throttle 5/min |
| POST | /logout | – | revokes current token |
| GET | /me | – | current user + role + permissions |
| POST | /forgot-password, /reset-password | | optional |

## Users & roles (permission `users.manage`, Admin only)

| Method | Path | Notes |
|---|---|---|
| GET | /users | `?search&role&is_active&page&per_page` |
| POST | /users | `name,email,password,role,is_active` |
| GET/PUT | /users/{id} | |
| DELETE | /users/{id} | soft delete; cannot delete self |
| GET | /roles | roles with permissions |
| PUT | /roles/{id}/permissions | `{permissions: [..]}` (`roles.manage`) |
| GET | /permissions | all permissions |
| GET | /users/assignable | id+name of active users who can be assigned purchases (used by dropdowns; Manager+Admin) |

## Clients

| Method | Path | Permission |
|---|---|---|
| GET | /clients | `clients.view` — `?search&is_active&page&per_page&sort&direction` |
| POST | /clients | `clients.manage` |
| GET | /clients/{id} | `clients.view` — includes stats: `total_orders, pending_orders, completed_orders, cancelled_orders, total_order_value, recent_orders[5]` |
| PUT | /clients/{id} | `clients.manage` (audited) |
| DELETE | /clients/{id} | `clients.manage` — soft delete/deactivate; blocked (or deactivate only) if open purchases exist |
| GET | /clients/lookup?q= | light autocomplete for purchase wizard (id, name, email) |

## Items

| Method | Path | Permission |
|---|---|---|
| GET | /items | `items.view` — `?search&status&low_stock&page&sort` |
| POST | /items | `items.manage` |
| GET | /items/{id} | `items.view` |
| PUT | /items/{id} | `items.manage` (audited incl. price/stock changes) |
| PATCH | /items/{id}/stock | `items.manage` — manual adjustment `{delta, reason}` |
| GET | /items/lookup?q= | active items for wizard (id, sku, name, price, stock, tax_rate) |

## Purchases

| Method | Path | Notes |
|---|---|---|
| GET | /purchases | list; Staff automatically scoped to `assigned_to = me` |
| POST | /purchases | create (Admin, Manager) |
| GET | /purchases/{id} | detail incl. client, items, status_history, notes, activities, allowed_transitions |
| PUT | /purchases/{id} | edit (Admin, Manager); only while status ∈ draft/pending (confirmed+ locks items); requires `version` |
| DELETE | /purchases/{id} | Admin only; soft delete; only draft/cancelled (else 422); audited |
| POST | /purchases/{id}/status | `{status, remarks?, version?}` |
| POST | /purchases/{id}/assign | `{assigned_to}` (Admin, Manager) |
| POST | /purchases/{id}/notes | `{body}` |
| GET | /purchases/{id}/notes, /purchases/{id}/activity, /purchases/{id}/history | paged sub-resources (also embedded in detail) |
| POST | /purchases/{id}/duplicate | bonus |
| POST | /purchases/preview | `items[], discount_type, discount_value, tax_rate?` → server-computed totals for wizard review step (no persistence) |

### Create/Update purchase payload
```json
{
  "customer_id": 12,
  "order_date": "2026-10-09",
  "priority": "high",
  "assigned_to": 3,
  "status": "draft",
  "discount_type": "fixed",
  "discount_value": 20,
  "notes": "Deliver before Friday",
  "items": [ {"product_id": 5, "quantity": 2}, {"product_id": 9, "quantity": 3} ],
  "version": 4
}
```
Client-sent `unit_price`, `subtotal`, `tax`, `total` are **ignored**. Initial status allowed: `draft` or `pending` only.

### List query params
`search` (purchase number | client name | client email | item name/SKU | assignee name), `status[]`, `priority[]`, `customer_id`, `assigned_to`, `date_from`, `date_to`, `sort` ∈ `order_date,total,status,priority,client,created_at`, `direction` asc|desc, `page`, `per_page` (≤100).

### List item shape (OrderListResource — slim)
`id, order_number, client{id,name}, order_date, status, priority, assigned_to{id,name}, total, items_count`

### Detail shape (OrderResource)
`id, order_number, status, priority, order_date, client{...full}, assigned_to, created_by, items[{product_id,name,sku,quantity,unit_price,discount_amount,tax_amount,line_total}], subtotal, discount_type, discount_value, discount_amount, tax_rate, tax_amount, total, notes, status_history[], notes_list[], activities[], allowed_transitions[], permissions{can_edit,can_delete,can_assign,can_change_status,can_cancel,can_add_note}, version, timestamps`.

`permissions{}` is computed server-side from policies so the UI only mirrors backend truth.

## Dashboard (`dashboard.view`: Admin, Manager)

| Method | Path | Notes |
|---|---|---|
| GET | /dashboard/summary | counts per status, total revenue (completed purchases), avg purchase value, total clients, total items |
| GET | /dashboard/status-distribution | `[{status,count}]` |
| GET | /dashboard/revenue-trend | `?period=day|week|month&from&to` → `[{label, revenue, purchases}]` |
| GET | /dashboard/top-items, /dashboard/recent-purchases | optional |

Revenue definition: sum of `total` of purchases with status `completed` (document this; alternative: confirmed+). Cache 60s per params.

## Reports (`reports.view`: Admin, Manager)

| Method | Path | Notes |
|---|---|---|
| GET | /reports | aggregated report payload for the active filters |
| GET | /reports/export | streams a downloadable file (`type`, `format`, filters) |

Shared filters (query params on both endpoints): `status`, `year`, `month` (1–12), `start_date`, `end_date`, `client_id`, `item_id`. `all` or empty means "no constraint"; dates filter on `purchase_date`.

`GET /reports` returns:
`summary{total_revenue,total_purchases,total_clients,total_items,total_items_sold,avg_order_value}`,
`purchases_by_status[{status,count,revenue}]`,
`top_products_by_quantity[]` and `top_products_by_price[]` (`item_id,item_name,sku,unit_price,current_stock,total_quantity,total_revenue,orders_count`),
`top_clients_by_orders[]` and `top_clients_by_price[]` (`client_id,client_name,email,phone,orders_count,total_spent`),
`per_client_report[]` (`client_id,client_name,email,phone,orders_count,total_spent,avg_order_value,latest_purchase_date,total_units_purchased`),
`per_product_report[]` (`item_id,item_name,sku,unit_price,current_stock,orders_count,total_quantity_sold,total_revenue`),
`filter_options{years[],months[{value,name}],statuses[],clients[{id,name,email}],items[{id,name,sku}]}`.

`GET /reports/export`:
- `type` ∈ `overview` (default), `top_products_quantity`, `top_products_price`, `top_clients_orders`, `top_clients_price`, `per_client`, `per_product`
- `format` ∈ `csv` (UTF-8 BOM), `xls` (BIFF8), `xlsx` (OOXML), `pdf` (DomPDF)
- applies the same filters and responds as an attachment (auth header required, so the SPA downloads via authenticated `fetch`, not a plain link).

## Audit logs (`audit.view`: Admin)

`GET /audit-logs?user_id&action&auditable_type&auditable_id&date_from&date_to&page`

## Notifications

| Method | Path |
|---|---|
| GET | /notifications `?unread=1` |
| GET | /notifications/unread-count |
| POST | /notifications/{id}/read |
| POST | /notifications/read-all |

## Permission matrix

| Permission | Admin | Manager | Staff |
|---|---|---|---|
| users.manage, roles.manage | ✔ | | |
| clients.view | ✔ | ✔ | ✔ |
| clients.manage | ✔ | ✔ (create/edit; no delete) | |
| items.view | ✔ | ✔ | ✔ |
| items.manage | ✔ | | |
| purchases.view_all | ✔ | ✔ | (own assigned only) |
| purchases.create / purchases.update | ✔ | ✔ | |
| purchases.delete | ✔ | | |
| purchases.assign | ✔ | ✔ | |
| purchases.change_status | ✔ | ✔ | ✔ (allowed statuses, assigned purchases) |
| purchases.add_note | ✔ | ✔ | ✔ (assigned purchases) |
| dashboard.view, reports.view | ✔ | ✔ | |
| audit.view | ✔ | | |

Justification of deviations: Manager may create/edit clients because sales flows need it; Admin-only item management matches the spec. Record in Technical Notes.

## Response shapes relied on by automated tests (keep stable)

| Endpoint | Contract |
|---|---|
| `GET /health` | public; `{success:true,...}` |
| `POST /login` | `data{token,user{id,name,email,role,permissions[]}}`; bad credentials → 401 `INVALID_CREDENTIALS`; throttled → 429 `TOO_MANY_REQUESTS`. Throttle limits come from `config('vms.login_throttle'|'api_throttle')`. |
| `GET /purchases` | `data[]` slim rows + `meta{current_page,per_page,total,last_page}`; unknown `sort` or `direction` → 422 `VALIDATION_ERROR` (never reaches SQL); `per_page` capped at 100 |
| `POST /purchases/preview` | Admin/Manager only; `data{subtotal,discount_amount,tax_rate,tax_amount,total}`; optional `tax_rate`; persists nothing; same validation as create items |
| Purchase detail | `status_history[{from_status,to_status,remarks,user{id,name},created_at}]`, `notes_list[{id,body,user,created_at}]`, `activities[]`, `items[{...,discount_amount,tax_amount,line_total}]`. Σ item discount / tax / line_total equals the purchase's discount / tax / total **to the cent** |
| `PUT /purchases/{id}` | requires `version`; stale → 409 `CONFLICT`; status not draft/pending → 422 |
| `POST /purchases/{id}/status` | confirm without stock → 422 `INSUFFICIENT_STOCK` (message names the items); cancelling confirmed+ without `remarks` → 422 on `remarks` |
| `GET /clients/{id}` | stats at top level of `data`: `total_orders, pending_orders, completed_orders, cancelled_orders, total_order_value` (excludes cancelled and draft), `recent_orders[≤5]` |
| `GET /dashboard/summary` | `total_orders` (all non-deleted), `pending_orders, confirmed_orders, processing_orders, completed_orders, cancelled_orders, total_revenue` (Σ completed totals), `average_order_value` (revenue ÷ completed count, 0 if none), `total_customers, total_products` |
| `GET /dashboard/revenue-trend` | zero-filled `[{label,revenue,purchases}]` by `order_date`; labels `YYYY-MM-DD` (day), `YYYY-Www` ISO week, `YYYY-MM` (month); invalid `period` → 422 |
| `GET /dashboard/status-distribution` | `[{status,count}]`, Σ count = `total_orders` |
| `GET /notifications` | `data[{id,read_at,created_at,data{type,message,order_id,order_number}}]`; `type ∈ order_assigned, order_confirmed, order_cancelled, low_stock` |
| `GET /notifications/unread-count` | `data{count}` |
| `POST /notifications/{id}/read` | 200; another user's notification → 404 |
| `GET /audit-logs` | `data[{id,action,auditable_type,auditable_id,label,old_values,new_values,user{id,name}\|null,created_at}]`, newest first; no write routes (PUT/DELETE → 404/405); never contains `password`/tokens |
