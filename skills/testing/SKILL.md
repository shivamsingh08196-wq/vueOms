---
name: testing
description: Use when writing or running Laravel backend tests, Vue component tests, JavaScript syntax checks, or browser tests for vueOMS.
---

# Testing skill

## Backend setup
- `phpunit.xml`: use MySQL `oms_test` (`DB_CONNECTION=mysql`, `DB_DATABASE=oms_test`) because the dashboard uses `DATE_FORMAT`/ISO-week SQL; `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`. It must be a different database from the e2e one (`oms_e2e`).
- Traits: `RefreshDatabase`. Helper `actingAsRole('admin'|'manager'|'staff')` that seeds `RolePermissionSeeder`, creates a user with the role and calls `Sanctum::actingAs`.
- Factories: `User, Client, Item, Purchase (states: draft(), pending(), confirmed(), completed()…), PurchaseLine`.
- Assert the envelope: `->assertJson(['success'=>true])`, errors: `->assertStatus(422)->assertJsonPath('code','VALIDATION_ERROR')`.

## Minimum backend test list (assessment §24 + extras)

| Area | Tests |
|---|---|
| Authentication | login ok returns token+user; wrong password 401/422; inactive user blocked; `/me`; protected route 401; logout revokes token; login throttle |
| Purchase creation | success with items, totals = 363 example; order_number generated; status history + audit + activity rows created; transaction rollback when stock/validation fails (no orphan purchase) |
| Purchase validation | client required/exists; ≥1 item; qty > 0; item exists/active; discount bounds; client totals ignored |
| Purchase update | only draft/pending; recalculates totals; stale `version` → 409; audit old/new |
| Status transition | each valid edge; history row; timestamps; confirm reserves stock; cancel restores; completed keeps stock |
| Invalid transition | completed→draft, cancelled→processing, draft→ready, same→same: 422 `INVALID_STATUS_TRANSITION` and no DB change |
| Authorization | staff cannot create/delete purchases, manage items/users; staff sees only assigned; staff allowed vs disallowed transitions; manager cannot access users/audit; admin can |
| Client creation | valid, duplicate email, validation, permission; stats on detail |
| Item creation | valid, duplicate SKU, negative price/stock, permission; stock adjust audited |
| Listing | search by each field; each filter; each sort; pagination meta; per_page cap; query count constant (no N+1) |
| Dashboard | counts/revenue/avg/trend from fixture data |
| Notifications | assigned notification; mark read; mark all read; scoped to user |
| Unit | `OrderPricingService` (350/20/33/363, percent, clamp, rounding), `OrderStatus::canTransitionTo` table-driven, `AuditLogger` diff |

Use data providers / `->with([...])` datasets for transition matrices.

## Example (Pest)
```php
it('rejects invalid status transitions', function () {
    actingAsRole('admin');
    $purchase = Purchase::factory()->completed()->create();
    postJson("/api/purchases/{$purchase->id}/status", ['status'=>'draft'])
        ->assertStatus(422)->assertJsonPath('code','INVALID_STATUS_TRANSITION');
    expect($purchase->fresh()->status)->toBe(OrderStatus::Completed);
});
```

## Frontend
Use Vitest + Vue Test Utils for `.vue` components and `.js` modules. Configure the test environment in Vite/Vitest according to the actual project. Keep specs beside components or under the documented test directory. Test observable behavior: loading, empty, error, validation, permissions and successful actions. Mock the API boundary rather than internal implementation details. Use Playwright for end-to-end flows.

## Commands
```
composer test              # Pest (./vendor/bin/pest --compact)
npm run test:unit          # Vitest
npm run lint && npm run typecheck && npm run typecheck:e2e && npm run build
npm run e2e                # Playwright (builds assets, seeds oms_e2e, serves on :8001)
composer qa                # everything
composer qa:dry            # verification only, 2 consecutive clean passes
```
Every phase ends with green tests; include the final test summary in README.
