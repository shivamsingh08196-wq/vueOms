<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\Item;

use function Pest\Laravel\deleteJson;
use function Pest\Laravel\getJson;
use function Pest\Laravel\putJson;

it('updates a pending purchase, recalculates totals and bumps the version', function () {
    actingAsRole('manager');
    $id = createOrderViaApi();
    $purchase = Purchase::find($id);
    $item = Item::factory()->create(['price' => 200, 'stock_quantity' => 50]);

    $res = putJson("/api/purchases/{$id}", [
        'version' => $purchase->version,
        'priority' => 'high',
        'items' => [['product_id' => $item->id, 'quantity' => 1]],
        'discount_type' => 'fixed',
        'discount_value' => 0,
    ])->assertOk();

    expect((float) $res->json('data.total'))->toBe(220.0) // 200 + 10% tax
        ->and($res->json('data.priority'))->toBe('high')
        ->and($res->json('data.version'))->toBe($purchase->version + 1)
        ->and($res->json('data.items'))->toHaveCount(1);
});

it('supports partial updates (only provided fields change)', function () {
    actingAsRole('admin');
    $id = createOrderViaApi();
    $before = Purchase::find($id);

    putJson("/api/purchases/{$id}", ['version' => $before->version, 'priority' => 'urgent'])->assertOk();

    $after = Purchase::find($id);
    expect($after->priority->value ?? $after->priority)->toBe('urgent')
        ->and((float) $after->total)->toBe((float) $before->total)
        ->and($after->items()->count())->toBe(2);
});

it('writes an audit entry containing only the changed keys with old and new values', function () {
    $user = actingAsRole('admin');
    $id = createOrderViaApi();

    putJson("/api/purchases/{$id}", ['version' => 1, 'priority' => 'high'])->assertOk();

    $log = AuditLog::where('action', 'purchase.updated')->where('auditable_id', $id)->latest('id')->first();
    expect($log)->not->toBeNull()
        ->and($log->user_id)->toBe($user->id)
        ->and($log->old_values['priority'])->toBe('normal')
        ->and($log->new_values['priority'])->toBe('high')
        ->and($log->new_values)->not->toHaveKey('customer_id');
});

it('does not write an audit entry when nothing changed', function () {
    actingAsRole('admin');
    $id = createOrderViaApi();
    $count = AuditLog::where('action', 'purchase.updated')->count();

    putJson("/api/purchases/{$id}", ['version' => 1, 'priority' => 'normal'])->assertOk();

    expect(AuditLog::where('action', 'purchase.updated')->count())->toBe($count);
});

it('rejects a stale version with 409 CONFLICT and changes nothing', function () {
    actingAsRole('admin');
    $id = createOrderViaApi();
    putJson("/api/purchases/{$id}", ['version' => 1, 'priority' => 'high'])->assertOk();

    putJson("/api/purchases/{$id}", ['version' => 1, 'priority' => 'low'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'CONFLICT');

    expect(Purchase::find($id)->priority->value ?? Purchase::find($id)->priority)->toBe('high');
});

it('requires the version field', function () {
    actingAsRole('admin');
    $id = createOrderViaApi();

    putJson("/api/purchases/{$id}", ['priority' => 'high'])->assertUnprocessable()->assertJsonValidationErrors('version');
});

it('only allows editing draft or pending purchases', function (string $status) {
    actingAsRole('admin');
    $purchase = Purchase::factory()->state(['status' => $status])->create();

    putJson("/api/purchases/{$purchase->id}", ['version' => $purchase->version, 'priority' => 'high'])
        ->assertUnprocessable();
})->with(['confirmed', 'processing', 'ready', 'completed', 'cancelled']);

it('lets only admins delete, and only draft or cancelled purchases', function () {
    actingAsRole('admin');
    $draft = Purchase::factory()->draft()->create();
    $pending = Purchase::factory()->pending()->create();
    $cancelled = Purchase::factory()->cancelled()->create();

    deleteJson("/api/purchases/{$pending->id}")->assertUnprocessable();
    deleteJson("/api/purchases/{$draft->id}")->assertOk();
    deleteJson("/api/purchases/{$cancelled->id}")->assertOk();

    $this->assertSoftDeleted('purchases', ['id' => $draft->id]);
    getJson("/api/purchases/{$draft->id}")->assertNotFound();
    expect(AuditLog::where('action', 'purchase.deleted')->where('auditable_id', $draft->id)->exists())->toBeTrue();
});

it('forbids managers from deleting purchases', function () {
    actingAsRole('manager');
    $draft = Purchase::factory()->draft()->create();

    deleteJson("/api/purchases/{$draft->id}")->assertForbidden();
});

it('returns 404 for unknown purchases in the error envelope', function () {
    actingAsRole('admin');

    getJson('/api/purchases/99999999')->assertNotFound()->assertJsonPath('code', 'NOT_FOUND')->assertJsonPath('success', false);
});

it('exposes allowed_transitions and permissions flags on the detail resource', function () {
    actingAsRole('manager');
    $id = createOrderViaApi();

    $res = getJson("/api/purchases/{$id}")->assertOk();

    expect($res->json('data.allowed_transitions'))->toContain('confirmed')
        ->and($res->json('data.permissions.can_edit'))->toBeTrue()
        ->and($res->json('data.permissions.can_delete'))->toBeFalse();
});
