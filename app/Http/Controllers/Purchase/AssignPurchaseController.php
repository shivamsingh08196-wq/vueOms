<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Http\Request;

class AssignPurchaseController extends Controller
{
    public function __invoke(Request $request, Purchase $purchase)
    {
        $user = auth()->user();
        if (! $user || (! $user->can('orders.assign') && ! $user->hasRole('admin') && ! $user->hasRole('manager'))) {
            abort(403, 'Unauthorized.');
        }

        $validated = $request->validate([
            'assigned_to' => 'nullable|exists:users,id',
        ]);

        $oldAssignee = $purchase->assigned_to;
        $purchase->assigned_to = $validated['assigned_to'] ?? null;
        $purchase->save();

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'purchase.assigned',
            'model_type' => Purchase::class,
            'model_id' => $purchase->id,
            'changes' => [
                'old_assigned_to' => $oldAssignee,
                'new_assigned_to' => $purchase->assigned_to,
            ],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Purchase assigned successfully',
            'data' => $purchase->load(['client', 'lines.item', 'assignee']),
        ]);
    }
}
