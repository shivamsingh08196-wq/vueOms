<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Item;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatePurchaseController extends Controller
{
    public function __invoke(Request $request)
    {
        $this->authorize('create', Purchase::class);

        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'purchase_date' => 'nullable|date',
            'purchase_number' => 'nullable|string|max:60|unique:purchases,purchase_number',
            'priority' => 'nullable|string|in:low,normal,high,urgent',
            'notes' => 'nullable|string',
            'assigned_to' => 'nullable|exists:users,id',
            'lines' => 'required|array|min:1',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'nullable|numeric|min:0',
        ]);

        return DB::transaction(function () use ($data) {
            $purchaseNumber = ! empty($data['purchase_number'])
                ? $data['purchase_number']
                : ('PUR-' . date('Ymd') . '-' . rand(1000, 9999));

            $purchase = Purchase::create([
                'client_id' => $data['client_id'],
                'purchase_date' => $data['purchase_date'] ?? now()->toDateString(),
                'purchase_number' => $purchaseNumber,
                'status' => 'pending',
                'priority' => $data['priority'] ?? 'normal',
                'notes' => $data['notes'] ?? null,
                'assigned_to' => $data['assigned_to'] ?? null,
                'total_amount' => 0,
            ]);

            $total = 0;
            foreach ($data['lines'] as $line) {
                $item = Item::findOrFail($line['item_id']);
                $unitPrice = isset($line['unit_price']) && is_numeric($line['unit_price'])
                    ? (float) $line['unit_price']
                    : (float) $item->price;

                $subtotal = $line['quantity'] * $unitPrice;
                $total += $subtotal;

                $purchase->lines()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal,
                ]);
            }

            $purchase->update(['total_amount' => $total]);

            AuditLog::create([
                'user_id' => auth()->id(),
                'action' => 'purchase.created',
                'model_type' => Purchase::class,
                'model_id' => $purchase->id,
                'changes' => [
                    'purchase_number' => $purchase->purchase_number,
                    'client_id' => $purchase->client_id,
                    'total_amount' => $total,
                    'lines_count' => count($data['lines']),
                ],
            ]);

            return response()->json($purchase->load(['client', 'lines.item', 'assignee']), 201);
        });
    }
}