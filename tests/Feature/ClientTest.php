<?php

use App\Models\User;
use App\Models\Client;

beforeEach(function () {
    $this->user = User::where('email', 'admin@vueoms.com')->first();
});

it('can fetch all clients', function () {
    $response = $this->actingAs($this->user)->getJson('/api/clients');
    $response->assertStatus(200);
});

it('can create a client', function () {
    $clientData = [
        'name' => 'Test Client 999',
        'email' => 'test999@client.com',
        'phone' => '1234567890',
        'address' => 'Test Address',
    ];

    $response = $this->actingAs($this->user)->postJson('/api/clients', $clientData);

    $response->assertStatus(201);
    $this->assertDatabaseHas('clients', ['email' => 'test999@client.com']);
});

it('can update a client', function () {
    $client = Client::first();
    $updateData = ['name' => 'Updated Name'];

    $response = $this->actingAs($this->user)->putJson("/api/clients/{$client->id}", $updateData);

    $response->assertStatus(200);
    $this->assertDatabaseHas('clients', [
        'id' => $client->id,
        'name' => 'Updated Name',
    ]);
});

it('can delete a client', function () {
    $client = Client::factory()->create();

    $response = $this->actingAs($this->user)->deleteJson("/api/clients/{$client->id}");

    $response->assertStatus(204);
    $this->assertDatabaseMissing('clients', [
        'id' => $client->id,
    ]);
});
