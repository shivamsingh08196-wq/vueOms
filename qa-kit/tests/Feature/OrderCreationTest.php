<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use App\Models\PurchaseStatusLog;
use App\Services\AuditLogger;

use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

it('creates an purchase with server-calculated totals (350 − 20 + 33 = 363)', function () {
    actingAsRole('manager');

    $res = postJson('/api/purchases', orderPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('message', 'Purchase created successfully');

    expect((float) $res->json('data.subtotal'))->toBe(350.0)
        ->and((float) $res->json('data.discount_amount'))->toBe(20.0)
        ->and((float) $res->json('data.tax_amount'))->toBe(33.0)
        ->and((float) $res->json('data.total'))->toBe(363.0)
        ->and($res->json('data.order_number'))->toMatch('/^ORD-\d+$/')
        ->and($res->json('data.items'))->toHaveCount(2);
});

it('ignores totals and prices sent by the client', function () {
    actingAsRole('admin');
    $payload = orderPayload(['total' => 1, 'subtotal' => 1, 'tax_amount' => 0, 'grand_total' => 1]);
    $payload['items'][0]['unit_price'] = 0.01;

    $res = postJson('/api/purchases', $payload)->assertCreated();

    expect((float) $res->json('data.total'))->toBe(363.0);
    expect((float) PurchaseLine::where('order_id', $res->json('data.id'))->orderBy('id')->first()->unit_price)->toBe(100.0);
});

it('snapshots item name, sku and unit price on each item', function () {
    actingAsRole('admin');
    $payload = orderPayload();
    $id = postJson('/api/purchases', $payload)->json('data.id');

    $item = \App\Models\Item::find($payload['items'][0]['product_id']);
    $item->update(['price' => 999, 'name' => 'Renamed']);

    $item = PurchaseLine::where('order_id', $id)->where('product_id', $item->id)->first();
    expect((float) $item->unit_price)->toBe(100.0)->and($item->product_name)->not->toBe('Renamed');
});

it('merges duplicate item rows into one line', function () {
    actingAsRole('admin');
    $payload = orderPayload();
    $pid = $payload['items'][0]['product_id'];
    $payload['items'] = [['product_id' => $pid, 'quantity' => 1], ['product_id' => $pid, 'quantity' => 2]];

    $id = postJson('/api/purchases', $payload)->assertCreated()->json('data.id');

    expect(PurchaseLine::where('order_id', $id)->count())->toBe(1)
        ->and(PurchaseLine::where('order_id', $id)->value('quantity'))->toBe(3);
});

it('writes status history, activity and audit rows for a new purchase', function () {
    $user = actingAsRole('manager');
    $id = postJson('/api/purchases', orderPayload())->json('data.id');

    $history = PurchaseStatusLog::where('order_id', $id)->first();
    expect($history)->not->toBeNull()
        ->and($history->from_status)->toBeNull()
        ->and($history->to_status)->toBe('pending')
        ->and($history->changed_by)->toBe($user->id);

    $audit = AuditLog::where('action', 'purchase.created')->where('auditable_id', $id)->first();
    expect($audit)->not->toBeNull()->and($audit->user_id)->toBe($user->id);

    getJson("/api/purchases/{$id}")->assertOk()->assertJsonCount(1, 'data.status_history');
});

it('does not touch stock when an purchase is created', function () {
    actingAsRole('admin');
    $payload = orderPayload();
    postJson('/api/purchases', $payload)->assertCreated();

    expect(\App\Models\Item::find($payload['items'][0]['product_id'])->stock_quantity)->toBe(100);
});

it('rolls back everything when a later step fails (no orphan purchase, items or history)', function () {
    actingAsRole('admin');
    $this->mock(AuditLogger::class, function ($mock) {
        $mock->shouldReceive('log')->andThrow(new RuntimeException('boom'));
    });

    postJson('/api/purchases', orderPayload())
        ->assertStatus(500)
        ->assertJsonPath('success', false)
        ->assertJsonPath('code', 'SERVER_ERROR')
        ->assertJsonMissingPath('trace');

    assertDatabaseCount('purchases', 0);
    assertDatabaseCount('purchase_lines', 0);
    assertDatabaseCount('order_status_histories', 0);
});

it('generates unique, sequential-looking purchase numbers', function () {
    actingAsRole('admin');
    $a = postJson('/api/purchases', orderPayload())->json('data.order_number');
    $b = postJson('/api/purchases', orderPayload())->json('data.order_number');

    expect($a)->not->toBe($b)->and(Purchase::where('order_number', $a)->count())->toBe(1);
});

it('applies a percentage discount', function () {
    actingAsRole('admin');
    $res = postJson('/api/purchases', orderPayload(['discount_type' => 'percent', 'discount_value' => 10]))->assertCreated();

    expect((float) $res->json('data.discount_amount'))->toBe(35.0)
        ->and((float) $res->json('data.tax_amount'))->toBe(31.5)
        ->and((float) $res->json('data.total'))->toBe(346.5);
});

it('stores the initial note as an purchase note', function () {
    actingAsRole('admin');
    $id = postJson('/api/purchases', orderPayload(['notes' => 'Deliver before Friday']))->json('data.id');

    expect(Purchase::find($id)->notes()->where('body', 'Deliver before Friday')->exists())->toBeTrue();
});

it('previews totals without saving anything', function () {
    actingAsRole('manager');
    $payload = orderPayload();

    postJson('/api/purchases/preview', ['items' => $payload['items'], 'discount_type' => 'fixed', 'discount_value' => 20])
        ->assertOk()
        ->assertJsonPath('data.total', fn ($v) => (float) $v === 363.0);

    assertDatabaseCount('purchases', 0);
});
