<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| VMS Pest bootstrap + shared helpers
|--------------------------------------------------------------------------
| ASSUMPTIONS (implement these in the app; the tests rely on them):
|  - Database\Seeders\RolePermissionSeeder is idempotent (firstOrCreate / findOrCreate) and creates
|    roles admin|manager|staff with the permissions listed in skills/rbac-policies.
|  - Factories: User, Client, Item (default active, price 10–200, stock 100),
|    Purchase (default: pending, 1–3 items created in afterCreating, stock_reserved=false, assigned_to null),
|    with Purchase states: draft(), pending(), confirmed(), processing(), ready(), completed(), cancelled().
|  - Response envelope: {success, message, data[, meta, links]} and {success:false, message, code, errors}.
|  - Money in JSON may be string or number — tests cast with (float).
*/

use App\Models\Client;
use App\Models\Item;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\postJson;

uses(Tests\TestCase::class, RefreshDatabase::class)->in('Feature', '../qa-kit/tests/Feature');
uses(Tests\TestCase::class)->in('Unit', '../qa-kit/tests/Unit');

const ALL_STATUSES = ['draft', 'pending', 'confirmed', 'processing', 'ready', 'completed', 'cancelled'];

/** The single source of truth for the state machine — mirrors docs/05-business-rules.md §2. */
function transitionMap(): array
{
    return [
        'draft' => ['pending', 'cancelled'],
        'pending' => ['confirmed', 'cancelled', 'draft'],
        'confirmed' => ['processing', 'cancelled'],
        'processing' => ['ready', 'cancelled'],
        'ready' => ['completed', 'cancelled'],
        'completed' => [],
        'cancelled' => [],
    ];
}

function seedRoles(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    test()->seed(RolePermissionSeeder::class);
}

function makeUser(string $role, array $attrs = []): User
{
    seedRoles();
    $user = User::factory()->create($attrs + ['is_active' => true]);
    $user->assignRole($role);

    return $user;
}

function actingAsRole(string $role, array $attrs = []): User
{
    $user = makeUser($role, $attrs);
    Sanctum::actingAs($user);

    return $user;
}

/** Valid create-purchase payload: the assessment example (2×100 + 3×50, −20, 10% tax = 363). */
function orderPayload(array $override = []): array
{
    $client = Client::factory()->create();
    $a = Item::factory()->create(['price' => 100, 'stock_quantity' => 100, 'status' => 'active']);
    $b = Item::factory()->create(['price' => 50, 'stock_quantity' => 100, 'status' => 'active']);

    return array_replace([
        'customer_id' => $client->id,
        'order_date' => now()->toDateString(),
        'priority' => 'normal',
        'status' => 'pending',
        'discount_type' => 'fixed',
        'discount_value' => 20,
        'items' => [
            ['product_id' => $a->id, 'quantity' => 2],
            ['product_id' => $b->id, 'quantity' => 3],
        ],
    ], $override);
}

/** Creates an purchase through the real API as the currently authenticated user and returns its id. */
function createOrderViaApi(array $override = []): int
{
    $res = postJson('/api/purchases', orderPayload($override))->assertCreated();

    return (int) $res->json('data.id');
}

function setStatusViaApi(int $orderId, string $status, ?string $remarks = null): \Illuminate\Testing\TestResponse
{
    return postJson("/api/purchases/{$orderId}/status", array_filter(['status' => $status, 'remarks' => $remarks]));
}
