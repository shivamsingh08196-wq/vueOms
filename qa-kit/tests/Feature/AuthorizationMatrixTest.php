<?php

declare(strict_types=1);

use App\Models\Client;
use App\Models\Purchase;
use App\Models\Item;

use function Pest\Laravel\getJson;
use function Pest\Laravel\postJson;

/*
 * Authorization must run BEFORE validation, so empty payloads still return 403 (not 422).
 * Matrix: docs/04-api-spec.md "Permission matrix".
 */

dataset('staff_forbidden', [
    ['POST', '/api/purchases'],
    ['POST', '/api/clients'],
    ['POST', '/api/items'],
    ['GET', '/api/users'],
    ['POST', '/api/users'],
    ['GET', '/api/roles'],
    ['GET', '/api/audit-logs'],
    ['GET', '/api/dashboard/summary'],
    ['GET', '/api/dashboard/revenue-trend'],
    ['GET', '/api/dashboard/status-distribution'],
]);

dataset('manager_forbidden', [
    ['GET', '/api/users'],
    ['POST', '/api/users'],
    ['GET', '/api/roles'],
    ['PUT', '/api/roles/1/permissions'],
    ['POST', '/api/items'],
    ['GET', '/api/audit-logs'],
]);

it('forbids staff on restricted endpoints', function (string $method, string $uri) {
    actingAsRole('staff');
    $this->json($method, $uri, [])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN')->assertJsonPath('success', false);
})->with('staff_forbidden');

it('forbids managers on admin-only endpoints', function (string $method, string $uri) {
    actingAsRole('manager');
    $this->json($method, $uri, [])->assertForbidden()->assertJsonPath('code', 'FORBIDDEN');
})->with('manager_forbidden');

it('lets admins reach everything in the matrix', function () {
    actingAsRole('admin');
    foreach (['/api/users', '/api/roles', '/api/audit-logs', '/api/dashboard/summary', '/api/clients', '/api/items', '/api/purchases'] as $uri) {
        getJson($uri)->assertOk();
    }
});

it('lets managers use purchases, dashboard, clients and items (read)', function () {
    actingAsRole('manager');
    foreach (['/api/purchases', '/api/dashboard/summary', '/api/clients', '/api/items', '/api/users/assignable'] as $uri) {
        getJson($uri)->assertOk();
    }
});

it('scopes staff to their assigned purchases in list and detail', function () {
    $staff = actingAsRole('staff');
    $mine = Purchase::factory()->confirmed()->create(['assigned_to' => $staff->id]);
    $theirs = Purchase::factory()->confirmed()->create(['assigned_to' => makeUser('staff')->id]);
    $unassigned = Purchase::factory()->pending()->create(['assigned_to' => null]);

    $ids = collect(getJson('/api/purchases?per_page=100')->assertOk()->json('data'))->pluck('id');

    expect($ids)->toContain($mine->id)->not->toContain($theirs->id)->not->toContain($unassigned->id);
    getJson("/api/purchases/{$mine->id}")->assertOk();
    getJson("/api/purchases/{$theirs->id}")->assertForbidden();
    getJson("/api/purchases/{$unassigned->id}")->assertForbidden();
});

it('prevents staff from bypassing scope with filters', function () {
    $staff = actingAsRole('staff');
    $other = makeUser('staff');
    $theirs = Purchase::factory()->create(['assigned_to' => $other->id]);

    $ids = collect(getJson("/api/purchases?assigned_to={$other->id}&per_page=100")->json('data'))->pluck('id');

    expect($ids)->not->toContain($theirs->id);
});

it('lets staff advance only their own purchases and only through allowed statuses', function () {
    $staff = actingAsRole('staff');
    $mine = Purchase::factory()->confirmed()->create(['assigned_to' => $staff->id]);
    $notMine = Purchase::factory()->confirmed()->create(['assigned_to' => makeUser('staff')->id]);

    setStatusViaApi($mine->id, 'processing')->assertOk();
    setStatusViaApi($mine->id, 'ready')->assertOk();
    setStatusViaApi($mine->id, 'cancelled', 'no')->assertForbidden();           // staff may not cancel
    setStatusViaApi($notMine->id, 'processing')->assertForbidden();             // not assigned
    $pending = Purchase::factory()->pending()->create(['assigned_to' => $staff->id]);
    setStatusViaApi($pending->id, 'confirmed')->assertForbidden();              // staff may not confirm
});

it('lets staff add notes to assigned purchases only', function () {
    $staff = actingAsRole('staff');
    $mine = Purchase::factory()->create(['assigned_to' => $staff->id]);
    $theirs = Purchase::factory()->create(['assigned_to' => makeUser('staff')->id]);

    postJson("/api/purchases/{$mine->id}/notes", ['body' => 'hello'])->assertCreated();
    postJson("/api/purchases/{$theirs->id}/notes", ['body' => 'hello'])->assertForbidden();
});

it('forbids staff from assigning or editing purchases', function () {
    $staff = actingAsRole('staff');
    $mine = Purchase::factory()->pending()->create(['assigned_to' => $staff->id]);

    postJson("/api/purchases/{$mine->id}/assign", ['assigned_to' => $staff->id])->assertForbidden();
    $this->putJson("/api/purchases/{$mine->id}", ['version' => $mine->version, 'priority' => 'high'])->assertForbidden();
    $this->deleteJson("/api/purchases/{$mine->id}")->assertForbidden();
});

it('lets staff view clients and items but not change them', function () {
    actingAsRole('staff');
    $client = Client::factory()->create();
    $item = Item::factory()->create();

    getJson('/api/clients')->assertOk();
    getJson("/api/clients/{$client->id}")->assertOk();
    getJson('/api/items')->assertOk();
    $this->putJson("/api/clients/{$client->id}", ['name' => 'x'])->assertForbidden();
    $this->putJson("/api/items/{$item->id}", ['name' => 'x'])->assertForbidden();
    $this->deleteJson("/api/clients/{$client->id}")->assertForbidden();
});

it('prevents an admin from removing core permissions from the admin role (lock-out protection)', function () {
    $admin = actingAsRole('admin');
    $role = \Spatie\Permission\Models\Role::findByName('admin');

    $this->putJson("/api/roles/{$role->id}/permissions", ['permissions' => ['purchases.view_all']])->assertUnprocessable();

    expect($admin->fresh()->can('users.manage'))->toBeTrue();
});

it('applies permission changes immediately (cache cleared) and audits them', function () {
    actingAsRole('admin');
    $staffRole = \Spatie\Permission\Models\Role::findByName('staff');
    $perms = $staffRole->permissions->pluck('name')->push('items.manage')->all();

    $this->putJson("/api/roles/{$staffRole->id}/permissions", ['permissions' => $perms])->assertOk();

    expect($staffRole->fresh()->hasPermissionTo('items.manage'))->toBeTrue()
        ->and(\App\Models\AuditLog::where('action', 'role.permissions_updated')->exists())->toBeTrue();
});

it('rejects permission names that do not exist', function () {
    actingAsRole('admin');
    $role = \Spatie\Permission\Models\Role::findByName('staff');

    $this->putJson("/api/roles/{$role->id}/permissions", ['permissions' => ['does.not.exist']])->assertUnprocessable();
});
