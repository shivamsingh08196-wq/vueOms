<?php

use App\Models\User;
use App\Models\Client;
use App\Models\Item;
use App\Models\Purchase;

beforeEach(function () {
    $this->admin = User::where('email', 'admin@vueoms.com')->first();
    $this->staff = User::where('email', 'staff@vueoms.com')->first();
});

it('reserves stock when purchase status changes to confirmed', function () {
    $client = Client::first();
    $item = Item::create([
        'name' => 'Stock Test Item',
        'sku' => 'STK-001',
        'price' => 50,
        'stock' => 20,
    ]);

    $purchase = Purchase::create([
        'client_id' => $client->id,
        'purchase_number' => 'PUR-STK-001',
        'purchase_date' => now()->toDateString(),
        'status' => 'pending',
        'total_amount' => 100,
        'stock_reserved' => false,
    ]);

    $purchase->lines()->create([
        'item_id' => $item->id,
        'quantity' => 5,
        'unit_price' => 50,
        'subtotal' => 250,
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/purchases/{$purchase->id}/status", [
        'status' => 'confirmed',
        'remarks' => 'Order confirmed',
    ]);

    $response->assertStatus(200);

    // Stock should be decremented from 20 to 15
    expect($item->fresh()->stock)->toBe(15);
    expect($purchase->fresh()->stock_reserved)->toBe(1);
});

it('prevents confirmation when item has insufficient stock', function () {
    $client = Client::first();
    $item = Item::create([
        'name' => 'Low Quantity Item',
        'sku' => 'STK-002',
        'price' => 50,
        'stock' => 2,
    ]);

    $purchase = Purchase::create([
        'client_id' => $client->id,
        'purchase_number' => 'PUR-STK-002',
        'purchase_date' => now()->toDateString(),
        'status' => 'pending',
        'total_amount' => 250,
        'stock_reserved' => false,
    ]);

    $purchase->lines()->create([
        'item_id' => $item->id,
        'quantity' => 10,
        'unit_price' => 50,
        'subtotal' => 500,
    ]);

    $response = $this->actingAs($this->admin)->postJson("/api/purchases/{$purchase->id}/status", [
        'status' => 'confirmed',
        'remarks' => 'Order confirmed',
    ]);

    $response->assertStatus(422);

    // Stock should remain unchanged
    expect($item->fresh()->stock)->toBe(2);
    expect($purchase->fresh()->stock_reserved)->toBe(0);
});

it('restores stock when a confirmed purchase is cancelled', function () {
    $client = Client::first();
    $item = Item::create([
        'name' => 'Restore Stock Item',
        'sku' => 'STK-003',
        'price' => 50,
        'stock' => 10,
    ]);

    $purchase = Purchase::create([
        'client_id' => $client->id,
        'purchase_number' => 'PUR-STK-003',
        'purchase_date' => now()->toDateString(),
        'status' => 'pending',
        'total_amount' => 150,
        'stock_reserved' => false,
    ]);

    $purchase->lines()->create([
        'item_id' => $item->id,
        'quantity' => 3,
        'unit_price' => 50,
        'subtotal' => 150,
    ]);

    // First confirm
    $this->actingAs($this->admin)->postJson("/api/purchases/{$purchase->id}/status", [
        'status' => 'confirmed',
        'remarks' => 'Confirmed order',
    ])->assertStatus(200);

    expect($item->fresh()->stock)->toBe(7);
    expect($purchase->fresh()->stock_reserved)->toBe(1);

    // Then cancel with remarks
    $cancelResponse = $this->actingAs($this->admin)->postJson("/api/purchases/{$purchase->id}/status", [
        'status' => 'cancelled',
        'remarks' => 'Customer requested cancellation',
    ]);

    $cancelResponse->assertStatus(200);

    // Stock should be restored from 7 back to 10
    expect($item->fresh()->stock)->toBe(10);
    expect($purchase->fresh()->stock_reserved)->toBe(0);
});

it('triggers low stock notification to admin when stock falls below threshold', function () {
    $client = Client::first();
    $item = Item::create([
        'name' => 'Threshold Item',
        'sku' => 'STK-004',
        'price' => 10,
        'stock' => 7,
    ]);

    $purchase = Purchase::create([
        'client_id' => $client->id,
        'purchase_number' => 'PUR-STK-004',
        'purchase_date' => now()->toDateString(),
        'status' => 'pending',
        'total_amount' => 30,
        'stock_reserved' => false,
    ]);

    $purchase->lines()->create([
        'item_id' => $item->id,
        'quantity' => 3, // 7 - 3 = 4 (<= 5)
        'unit_price' => 10,
        'subtotal' => 30,
    ]);

    $this->admin->notifications()->delete();

    $this->actingAs($this->staff)->postJson("/api/purchases/{$purchase->id}/status", [
        'status' => 'confirmed',
        'remarks' => 'Confirmed order',
    ])->assertStatus(200);

    expect($item->fresh()->stock)->toBe(4);

    // Admin should have received LowStockNotification
    $notifications = $this->admin->fresh()->notifications()->get();
    $lowStockNotif = $notifications->first(fn ($n) => ($n->data['type'] ?? '') === 'low_stock');
    expect($lowStockNotif)->not->toBeNull();
    expect($lowStockNotif->data['item_id'])->toBe($item->id);
});

