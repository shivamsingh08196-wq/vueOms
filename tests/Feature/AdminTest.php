<?php

use App\Models\User;
use App\Models\Purchase;
use App\Models\Client;
use App\Models\Item;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->admin = User::where('email', 'admin@vueoms.com')->first();
    $this->manager = User::where('email', 'manager@vueoms.com')->first();
    $this->staff = User::where('email', 'staff@vueoms.com')->first();
});

it('admin can list users', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/users');
    $response->assertStatus(200);
    $response->assertJsonStructure(['data']);
});

it('admin can create a user with a role', function () {
    $payload = [
        'name' => 'New Staff Member',
        'email' => 'newstaff@vueoms.com',
        'password' => 'password123',
        'role' => 'staff',
    ];

    $response = $this->actingAs($this->admin)->postJson('/api/users', $payload);
    $response->assertStatus(201);
    $this->assertDatabaseHas('users', ['email' => 'newstaff@vueoms.com']);
});

it('admin cannot delete own account', function () {
    $response = $this->actingAs($this->admin)->deleteJson("/api/users/{$this->admin->id}");
    $response->assertStatus(422)
             ->assertJsonPath('message', 'Cannot delete your own account.');
});

it('admin can list roles and permissions', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/roles');
    $response->assertStatus(200)
             ->assertJsonStructure(['roles', 'all_permissions']);
});

it('enforces lockout protection when editing admin role permissions', function () {
    $adminRole = Role::where('name', 'admin')->first();

    // Attempting to remove users.manage
    $response = $this->actingAs($this->admin)->putJson("/api/roles/{$adminRole->id}/permissions", [
        'permissions' => ['view', 'create'],
    ]);

    $response->assertStatus(422)
             ->assertJsonPath('success', false);
});

it('admin can view audit logs', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/audit-logs');
    $response->assertStatus(200);
});

it('admin can view reports', function () {
    $response = $this->actingAs($this->admin)->getJson('/api/reports');
    $response->assertStatus(200)
             ->assertJsonStructure(['summary', 'purchases_by_status', 'top_clients']);
});

it('admin can change purchase status and record history', function () {
    $purchase = Purchase::first();
    $response = $this->actingAs($this->admin)->postJson("/api/purchases/{$purchase->id}/status", [
        'status' => 'confirmed',
        'remarks' => 'Approved by admin',
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'status' => 'confirmed']);
    $this->assertDatabaseHas('purchase_status_logs', ['purchase_id' => $purchase->id, 'new_status' => 'confirmed']);
});

it('admin can assign a purchase to a user', function () {
    $purchase = Purchase::first();
    $response = $this->actingAs($this->admin)->postJson("/api/purchases/{$purchase->id}/assign", [
        'assigned_to' => $this->staff->id,
    ]);

    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    $this->assertDatabaseHas('purchases', ['id' => $purchase->id, 'assigned_to' => $this->staff->id]);
});
