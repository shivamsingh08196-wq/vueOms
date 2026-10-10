<?php

use App\Models\User;
use App\Models\Client;
use App\Models\Item;
use App\Models\Purchase;

beforeEach(function () {
    $this->user = User::where('email', 'admin@vueoms.com')->first();
});

it('can fetch all purchases', function () {
    $response = $this->actingAs($this->user)->getJson('/api/purchases');
    $response->assertStatus(200);
});

it('can create a purchase', function () {
    $client = Client::factory()->create();
    $item = Item::factory()->create(['price' => 100]);

    $purchaseData = [
        'client_id' => $client->id,
        'purchase_number' => 'PUR-999',
        'purchase_date' => '2026-01-01',
        'status' => 'New',
        'lines' => [
            [
                'item_id' => $item->id,
                'quantity' => 2,
                'unit_price' => 100,
            ]
        ],
        'comments' => [
            ['comment' => 'Test comment']
        ]
    ];

    $response = $this->actingAs($this->user)->postJson('/api/purchases', $purchaseData);

    $response->assertStatus(201);
    $this->assertDatabaseHas('purchases', ['client_id' => $client->id, 'total_amount' => 200]);
    $this->assertDatabaseHas('purchase_lines', ['item_id' => $item->id, 'quantity' => 2]);
});

it('can update a purchase', function () {
    $purchase = Purchase::first();
    $updateData = ['status' => 'In Progress'];

    $response = $this->actingAs($this->user)->putJson("/api/purchases/{$purchase->id}", $updateData);

    $response->assertStatus(200);
    $this->assertDatabaseHas('purchases', [
        'id' => $purchase->id,
        'status' => 'In Progress',
    ]);
});

it('can delete a purchase', function () {
    $purchase = Purchase::factory()->create();

    $response = $this->actingAs($this->user)->deleteJson("/api/purchases/{$purchase->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('purchases', [
        'id' => $purchase->id,
    ]);
});
