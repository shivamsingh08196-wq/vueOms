<?php

use App\Models\User;
use App\Models\Purchase;
use App\Notifications\OrderAssignedNotification;

beforeEach(function () {
    $this->admin = User::where('email', 'admin@vueoms.com')->first();
    $this->manager = User::where('email', 'manager@vueoms.com')->first();
    $this->staff = User::where('email', 'staff@vueoms.com')->first();
});

it('can fetch unread notifications count', function () {
    $purchase = Purchase::first();
    $this->staff->notify(new OrderAssignedNotification($purchase));

    $response = $this->actingAs($this->staff)->getJson('/api/notifications/unread-count');
    $response->assertStatus(200)
             ->assertJsonStructure(['data' => ['count']]);

    expect($response->json('data.count'))->toBeGreaterThanOrEqual(1);
});

it('can fetch notifications with correct data shape', function () {
    $purchase = Purchase::first();
    $this->staff->notify(new OrderAssignedNotification($purchase));

    $response = $this->actingAs($this->staff)->getJson('/api/notifications');
    $response->assertStatus(200)
             ->assertJsonStructure([
                 'data' => [
                     '*' => [
                         'id',
                         'read_at',
                         'created_at',
                         'data' => [
                             'type',
                             'order_id',
                             'order_number',
                             'message',
                         ],
                     ],
                 ],
             ]);
});

it('can mark a notification as read and returns 404 for another users notification', function () {
    $purchase = Purchase::first();
    $this->staff->notify(new OrderAssignedNotification($purchase));

    $notification = $this->staff->unreadNotifications()->first();
    expect($notification)->not->toBeNull();

    // Another user tries to mark it as read -> 404
    $forbiddenResponse = $this->actingAs($this->manager)->postJson("/api/notifications/{$notification->id}/read");
    $forbiddenResponse->assertStatus(404);

    // Owner marks it as read -> 200
    $successResponse = $this->actingAs($this->staff)->postJson("/api/notifications/{$notification->id}/read");
    $successResponse->assertStatus(200)
                    ->assertJsonPath('success', true);

    expect($this->staff->fresh()->unreadNotifications()->count())->toBe(0);
});

it('can mark all notifications as read', function () {
    $purchase = Purchase::first();
    $this->staff->notify(new OrderAssignedNotification($purchase));
    $this->staff->notify(new OrderAssignedNotification($purchase));

    expect($this->staff->unreadNotifications()->count())->toBeGreaterThanOrEqual(2);

    $response = $this->actingAs($this->staff)->postJson('/api/notifications/read-all');
    $response->assertStatus(200)
             ->assertJsonPath('success', true);

    expect($this->staff->fresh()->unreadNotifications()->count())->toBe(0);
});

it('notifies admin when assigned user updates purchase status', function () {
    $purchase = Purchase::first();
    $purchase->assigned_to = $this->staff->id;
    $purchase->status = 'pending';
    $purchase->save();

    $this->admin->notifications()->delete();
    expect($this->admin->unreadNotifications()->count())->toBe(0);

    // Assigned staff changes purchase status
    $response = $this->actingAs($this->staff)->postJson("/api/purchases/{$purchase->id}/status", [
        'status' => 'confirmed',
        'remarks' => 'Staff confirmed the order',
    ]);
    $response->assertStatus(200);

    // Admin should have received a notification
    $unreadNotifications = $this->admin->fresh()->unreadNotifications()->get();
    expect($unreadNotifications->count())->toBeGreaterThanOrEqual(1);
    expect($unreadNotifications->first()->data['order_id'])->toBe($purchase->id);
    expect($unreadNotifications->first()->data['type'])->toBe('order_confirmed');
});


