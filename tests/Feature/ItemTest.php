<?php

use App\Models\User;
use App\Models\Item;

beforeEach(function () {
    $this->user = User::where('email', 'admin@vueoms.com')->first();
});

it('can fetch all items', function () {
    $response = $this->actingAs($this->user)->getJson('/api/items');
    $response->assertStatus(200);
});

it('can create an item', function () {
    $itemData = [
        'name' => 'Test Item 999',
        'description' => 'Test Item Description',
        'price' => 99.99,
        'stock' => 10,
    ];

    $response = $this->actingAs($this->user)->postJson('/api/items', $itemData);

    $response->assertStatus(201);
    $this->assertDatabaseHas('items', ['name' => 'Test Item 999']);
});

it('can update an item', function () {
    $item = Item::first();
    $updateData = ['price' => 199.99];

    $response = $this->actingAs($this->user)->putJson("/api/items/{$item->id}", $updateData);

    $response->assertStatus(200);
    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'price' => 199.99,
    ]);
});

it('can delete an item', function () {
    $item = Item::factory()->create();

    $response = $this->actingAs($this->user)->deleteJson("/api/items/{$item->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('items', [
        'id' => $item->id,
    ]);
});
