<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\PurchaseStatusLog;
use Illuminate\Http\Request;

class ChangePurchaseStatusController extends Controller
{
    public function __invoke(Request $request, Purchase $purchase)
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

        $purchase->status = $newStatus;
        $purchase->save();

        PurchaseStatusLog::create([
            'purchase_id' => $purchase->id,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
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

        return response()->json([
            'success' => true,
            'message' => "Purchase status updated to {$newStatus}",
            'data' => $purchase->load(['client', 'lines.item']),
        ]);
    }
}
