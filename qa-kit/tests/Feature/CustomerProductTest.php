<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Purchase;
use App\Models\Item;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\patchJson;
use function Pest\Laravel\postJson;
use function Pest\Laravel\putJson;

/* ---------------------------------------------------------------- clients */

it('creates a client', function () {
    actingAsRole('manager');

    postJson('/api/clients', ['name' => 'Acme Corp', 'email' => 'ACME@Example.test', 'phone' => '+91 99999 00000', 'city' => 'Noida'])
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.name', 'Acme Corp');

    $this->assertDatabaseHas('clients', ['name' => 'Acme Corp']);
});

it('validates clients', function (array $payload, array $errors) {
    actingAsRole('admin');
    Client::factory()->create(['email' => 'taken@example.test']);

    postJson('/api/clients', $payload)->assertUnprocessable()->assertJsonValidationErrors($errors);
})->with([
    'empty' => [[], ['name', 'email']],
    'bad email' => [['name' => 'A', 'email' => 'nope'], ['email']],
    'duplicate email' => [['name' => 'A', 'email' => 'taken@example.test'], ['email']],
    'duplicate email (case-insensitive)' => [['name' => 'A', 'email' => 'TAKEN@example.test'], ['email']],
    'name too long' => [['name' => str_repeat('a', 300), 'email' => 'a@b.test'], ['name']],
]);

it('updates a client and audits old/new values', function () {
    $user = actingAsRole('manager');
    $c = Client::factory()->create(['phone' => '111']);

    putJson("/api/clients/{$c->id}", ['name' => $c->name, 'email' => $c->email, 'phone' => '222'])->assertOk();

    $log = AuditLog::where('action', 'client.updated')->where('auditable_id', $c->id)->first();
    expect($log->old_values)->toBe(['phone' => '111'])->and($log->new_values)->toBe(['phone' => '222'])->and($log->user_id)->toBe($user->id);
});

it('shows client statistics (cancelled excluded from value, drafts excluded)', function () {
    actingAsRole('manager');
    $c = Client::factory()->create();
    Purchase::factory()->pending()->create(['customer_id' => $c->id, 'total' => 110]);
    Purchase::factory()->completed()->create(['customer_id' => $c->id, 'total' => 220]);
    Purchase::factory()->cancelled()->create(['customer_id' => $c->id, 'total' => 500]);
    Purchase::factory()->draft()->create(['customer_id' => $c->id, 'total' => 900]);
    Purchase::factory()->confirmed()->create(); // someone else's

    $d = getJson("/api/clients/{$c->id}")->assertOk()->json('data');

    expect($d['total_orders'])->toBe(4)
        ->and($d['pending_orders'])->toBe(1)
        ->and($d['completed_orders'])->toBe(1)
        ->and($d['cancelled_orders'])->toBe(1)
        ->and((float) $d['total_order_value'])->toBe(330.0)
        ->and($d['recent_orders'])->toHaveCount(4);
});

it('searches and filters clients', function () {
    actingAsRole('manager');
    Client::factory()->create(['name' => 'Findable Ltd', 'is_active' => true]);
    Client::factory()->create(['name' => 'Dormant Ltd', 'is_active' => false]);

    expect(collect(getJson('/api/clients?search=Findable')->json('data'))->pluck('name'))->toContain('Findable Ltd')->not->toContain('Dormant Ltd');
    expect(collect(getJson('/api/clients?is_active=0')->json('data'))->pluck('name'))->toContain('Dormant Ltd')->not->toContain('Findable Ltd');
});

it('deactivates instead of destroying clients that have purchases', function () {
    actingAsRole('admin');
    $c = Client::factory()->create();
    $purchase = Purchase::factory()->create(['customer_id' => $c->id]);

    deleteJson("/api/clients/{$c->id}")->assertStatus(200);

    expect(Purchase::find($purchase->id))->not->toBeNull();
    expect(Client::withTrashed()->find($c->id))->not->toBeNull();
    getJson('/api/clients/lookup?q=' . urlencode($c->name))->assertOk()->assertJsonMissing(['id' => $c->id]);
});

/* ----------------------------------------------------------------- items */

it('creates a item (admin only)', function () {
    actingAsRole('admin');

    postJson('/api/items', ['name' => 'Widget', 'sku' => 'WID-1', 'price' => 19.99, 'stock_quantity' => 25, 'status' => 'active'])
        ->assertCreated()->assertJsonPath('data.sku', 'WID-1');

    $this->assertDatabaseHas('items', ['sku' => 'WID-1']);
});

it('validates items', function (array $payload, array $errors) {
    actingAsRole('admin');
    Item::factory()->create(['sku' => 'TAKEN-1']);

    postJson('/api/items', $payload)->assertUnprocessable()->assertJsonValidationErrors($errors);
})->with([
    'empty' => [[], ['name', 'sku', 'price', 'stock_quantity']],
    'duplicate sku' => [['name' => 'x', 'sku' => 'TAKEN-1', 'price' => 1, 'stock_quantity' => 1], ['sku']],
    'negative price' => [['name' => 'x', 'sku' => 'N-1', 'price' => -1, 'stock_quantity' => 1], ['price']],
    'negative stock' => [['name' => 'x', 'sku' => 'N-2', 'price' => 1, 'stock_quantity' => -1], ['stock_quantity']],
    'non numeric price' => [['name' => 'x', 'sku' => 'N-3', 'price' => 'abc', 'stock_quantity' => 1], ['price']],
    'bad status' => [['name' => 'x', 'sku' => 'N-4', 'price' => 1, 'stock_quantity' => 1, 'status' => 'maybe'], ['status']],
]);

it('forbids managers and staff from creating items', function (string $role) {
    actingAsRole($role);

    postJson('/api/items', ['name' => 'x', 'sku' => 'F-1', 'price' => 1, 'stock_quantity' => 1])->assertForbidden();
})->with(['manager', 'staff']);

it('updates a item and audits price changes', function () {
    actingAsRole('admin');
    $p = Item::factory()->create(['price' => 10, 'name' => 'Old']);

    putJson("/api/items/{$p->id}", ['name' => 'Old', 'sku' => $p->sku, 'price' => 15, 'stock_quantity' => $p->stock_quantity, 'status' => 'active'])->assertOk();

    $log = AuditLog::where('action', 'item.updated')->where('auditable_id', $p->id)->first();
    expect((float) $log->old_values['price'])->toBe(10.0)->and((float) $log->new_values['price'])->toBe(15.0);
});

it('lets the same SKU stay on update but not collide with another item', function () {
    actingAsRole('admin');
    $a = Item::factory()->create();
    $b = Item::factory()->create();
    $payload = fn (Item $p, string $sku) => ['name' => $p->name, 'sku' => $sku, 'price' => 5, 'stock_quantity' => 5, 'status' => 'active'];

    putJson("/api/items/{$a->id}", $payload($a, $a->sku))->assertOk();
    putJson("/api/items/{$a->id}", $payload($a, $b->sku))->assertUnprocessable()->assertJsonValidationErrors('sku');
});

it('filters items by status, search and low stock; lookup returns active items only', function () {
    actingAsRole('manager');
    Item::factory()->create(['name' => 'Alpha Active', 'status' => 'active', 'stock_quantity' => 2, 'low_stock_threshold' => 5]);
    Item::factory()->create(['name' => 'Alpha Inactive', 'status' => 'inactive', 'stock_quantity' => 100]);

    expect(collect(getJson('/api/items?search=Alpha&status=inactive')->json('data'))->pluck('name')->all())->toBe(['Alpha Inactive']);
    expect(collect(getJson('/api/items?low_stock=1')->json('data'))->pluck('name'))->toContain('Alpha Active');
    expect(collect(getJson('/api/items/lookup?q=Alpha')->json('data'))->pluck('name')->all())->toBe(['Alpha Active']);
});

it('keeps purchase history readable after a item is edited or deactivated', function () {
    actingAsRole('admin');
    $id = createOrderViaApi();
    $item = Purchase::find($id)->items()->first();
    Item::find($item->product_id)->update(['status' => 'inactive', 'price' => 1]);

    $d = getJson("/api/purchases/{$id}")->assertOk()->json('data');

    expect((float) $d['total'])->toBe(363.0);
});

it('adjusts stock by delta and rejects a result below zero', function () {
    actingAsRole('admin');
    $p = Item::factory()->create(['stock_quantity' => 4]);

    patchJson("/api/items/{$p->id}/stock", ['delta' => 6, 'reason' => 'restock'])->assertOk();
    patchJson("/api/items/{$p->id}/stock", ['delta' => -11, 'reason' => 'too much'])->assertUnprocessable();

    expect($p->fresh()->stock_quantity)->toBe(10);
});
