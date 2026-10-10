<?php

use App\Models\User;

it('can authenticate a user', function () {
    $user = User::where('email', 'admin@vueoms.com')->first();

    $response = $this->postJson('/api/login', [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertStatus(200);
    $response->assertJsonStructure(['token', 'user']);
});

it('can fetch authenticated user details', function () {
    $user = User::where('email', 'admin@vueoms.com')->first();

    $response = $this->actingAs($user)->getJson('/api/me');

    $response->assertStatus(200);
    $response->assertJsonStructure(['id', 'name', 'email', 'roles']);
});

it('can logout a user', function () {
    $user = User::where('email', 'admin@vueoms.com')->first();
    $token = $user->createToken('test')->plainTextToken;

    $response = $this->withHeaders(['Authorization' => "Bearer $token"])->postJson('/api/logout');

    $response->assertStatus(200);
});
