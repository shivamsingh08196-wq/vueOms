<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\PurchaseStatusLog;
use App\Models\User;
use App\Services\StockService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ChangePurchaseStatusController extends Controller
{
    public function __invoke(Request $request, Purchase $purchase, StockService $stockService)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('orders.change_status') && ! $user->hasRole('admin') && ! $user->hasRole('manager'))) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'status' => 'required|string|in:draft,pending,confirmed,processing,ready,completed,cancelled',
            'remarks' => 'nullable|string|max:500',
        ]);

        $oldStatus = $purchase->status;
        $newStatus = $validated['status'];

        // Enforce remarks requirement when cancelling a confirmed+ order
        if ($newStatus === 'cancelled' && in_array($oldStatus, ['confirmed', 'processing', 'ready'], true) && empty($validated['remarks'])) {
            return response()->json([
                'message' => 'Remarks are required when cancelling a confirmed order.',
                'errors' => [
                    'remarks' => ['Remarks are required when cancelling a confirmed order.'],
                ],
            ], 422);
        }

        DB::transaction(function () use ($purchase, $oldStatus, $newStatus, $validated, $user, $stockService) {
            // Update inventory stock based on status transition
            if (in_array($newStatus, ['confirmed', 'processing', 'ready', 'completed'], true)) {
                $stockService->reserve($purchase);
            } elseif ($newStatus === 'cancelled') {
                $stockService->restore($purchase);
            }

            $purchase->status = $newStatus;
            $purchase->save();

            PurchaseStatusLog::create([
                'purchase_id' => $purchase->id,
                'old_status' => $oldStatus,
                'new_status' => $newStatus,
                'remarks' => $validated['remarks'] ?? null,
                'changed_by_user_id' => $user->id,
            ]);

            AuditLog::create([
                'user_id' => $user->id,
                'action' => 'purchase.status_changed',
                'model_type' => Purchase::class,
                'model_id' => $purchase->id,
                'changes' => [
                    'from' => $oldStatus,
                    'to' => $newStatus,
                    'remarks' => $validated['remarks'] ?? null,
                ],
            ]);
        });

        $recipients = collect();

        // 1. If actor is not the assignee, notify the assignee
        if ($purchase->assigned_to && $purchase->assigned_to !== $user->id) {
            $assignee = \App\Models\User::find($purchase->assigned_to);
            if ($assignee) {
                $recipients->push($assignee);
            }
        }

        // 2. Admins should always be notified when another user (e.g. assigned user) updates the status
        $admins = \App\Models\User::role('admin')->where('id', '!=', $user->id)->get();
        foreach ($admins as $adminUser) {
            if (! $recipients->contains('id', $adminUser->id)) {
                $recipients->push($adminUser);
            }
        }

        // 3. For cancellations, also notify managers
        if ($newStatus === 'cancelled') {
            $managers = \App\Models\User::role('manager')->where('id', '!=', $user->id)->get();
            foreach ($managers as $m) {
                if (! $recipients->contains('id', $m->id)) {
                    $recipients->push($m);
                }
            }
        }

        $notificationType = match ($newStatus) {
            'confirmed' => 'order_confirmed',
            'cancelled' => 'order_cancelled',
            default => 'order_status_updated',
        };

        $message = match ($newStatus) {
            'confirmed' => "Purchase #{$purchase->purchase_number} has been confirmed.",
            'cancelled' => "Purchase #{$purchase->purchase_number} was cancelled.",
            default => "Purchase #{$purchase->purchase_number} status updated to {$newStatus} by {$user->name}.",
        };

        foreach ($recipients as $recipient) {
            $recipient->notify(new \App\Notifications\OrderStatusNotification(
                $purchase,
                $newStatus,
                $notificationType,
                $message
            ));
        }

        return response()->json([
            'success' => true,
            'message' => "Purchase status updated to {$newStatus}",
            'data' => $purchase->load(['client', 'lines.item']),
        ]);
    }
}
