<?php

declare(strict_types=1);

use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\PurchaseStatusLog;

use function Pest\Laravel\getJson;

dataset('valid_transitions', function () {
    foreach (transitionMap() as $from => $targets) {
        foreach ($targets as $to) {
            yield "{$from} → {$to}" => [$from, $to];
        }
    }
});

dataset('invalid_transitions', function () {
    foreach (transitionMap() as $from => $targets) {
        foreach (ALL_STATUSES as $to) {
            if (! in_array($to, $targets, true)) {
                yield "{$from} → {$to}" => [$from, $to];
            }
        }
    }
});

it('performs every valid transition (admin) and records history + audit', function (string $from, string $to) {
    $admin = actingAsRole('admin');
    $purchase = Purchase::factory()->state(['status' => $from])->create();

    setStatusViaApi($purchase->id, $to, in_array($to, ['cancelled']) ? 'Client request' : 'Moving on')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.status', $to);

    $history = PurchaseStatusLog::where('order_id', $purchase->id)->latest('id')->first();
    expect($history->from_status)->toBe($from)
        ->and($history->to_status)->toBe($to)
        ->and($history->changed_by)->toBe($admin->id);

    expect(AuditLog::where('action', 'purchase.status_changed')->where('auditable_id', $purchase->id)->exists())->toBeTrue();
})->with('valid_transitions');

it('rejects every invalid transition with 422 INVALID_STATUS_TRANSITION and changes nothing', function (string $from, string $to) {
    actingAsRole('admin');
    $purchase = Purchase::factory()->state(['status' => $from])->create();
    $historyCount = PurchaseStatusLog::where('order_id', $purchase->id)->count();

    setStatusViaApi($purchase->id, $to, 'x')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'INVALID_STATUS_TRANSITION');

    expect($purchase->fresh()->status->value ?? $purchase->fresh()->status)->toBe($from)
        ->and(PurchaseStatusLog::where('order_id', $purchase->id)->count())->toBe($historyCount);
})->with('invalid_transitions');

it('rejects unknown status values with a validation error', function () {
    actingAsRole('admin');
    $purchase = Purchase::factory()->pending()->create();

    setStatusViaApi($purchase->id, 'teleported')->assertUnprocessable()->assertJsonValidationErrors('status');
});

it('requires a reason when cancelling after confirmation', function (string $from) {
    actingAsRole('admin');
    $purchase = Purchase::factory()->state(['status' => $from])->create();

    setStatusViaApi($purchase->id, 'cancelled')->assertUnprocessable()->assertJsonValidationErrors('remarks');
    setStatusViaApi($purchase->id, 'cancelled', 'Out of stock at supplier')->assertOk();
})->with(['confirmed', 'processing', 'ready']);

it('stores remarks and timestamps on the purchase', function () {
    actingAsRole('admin');
    $purchase = Purchase::factory()->pending()->create();

    setStatusViaApi($purchase->id, 'confirmed', 'Client confirmed the purchase.')->assertOk();
    $purchase->refresh();

    expect($purchase->confirmed_at)->not->toBeNull();
    $res = getJson("/api/purchases/{$purchase->id}")->assertOk();
    $entry = collect($res->json('data.status_history'))->firstWhere('to_status', 'confirmed');
    expect($entry['remarks'])->toBe('Client confirmed the purchase.')->and($entry['from_status'])->toBe('pending');
});

it('refuses to leave draft when the purchase has no items', function () {
    actingAsRole('admin');
    $purchase = Purchase::factory()->draft()->create();
    $purchase->items()->delete();

    setStatusViaApi($purchase->id, 'pending')->assertUnprocessable();
});

it('bumps the version on every transition', function () {
    actingAsRole('admin');
    $purchase = Purchase::factory()->pending()->create();
    $v = $purchase->version;

    setStatusViaApi($purchase->id, 'confirmed')->assertOk();

    expect($purchase->fresh()->version)->toBe($v + 1);
});

it('rejects a stale version supplied with a status change', function () {
    actingAsRole('admin');
    $purchase = Purchase::factory()->pending()->create();

    $this->postJson("/api/purchases/{$purchase->id}/status", ['status' => 'confirmed', 'version' => $purchase->version + 5])
        ->assertStatus(409)->assertJsonPath('code', 'CONFLICT');
});
