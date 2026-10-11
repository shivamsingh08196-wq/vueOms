<?php
namespace App\Http\Controllers\Purchase;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use App\Models\PurchaseLine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreatePurchaseController extends Controller {
    public function __invoke(Request $request) {
        $this->authorize('create', Purchase::class);
        $data = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'purchase_date' => 'required|date',
            'purchase_number' => 'required|unique:purchases,purchase_number',
            'lines' => 'required|array',
            'lines.*.item_id' => 'required|exists:items,id',
            'lines.*.quantity' => 'required|integer|min:1',
            'lines.*.unit_price' => 'required|numeric|min:0',
        ]);
        
        return DB::transaction(function() use ($data) {
            $purchase = Purchase::create([
                'client_id' => $data['client_id'],
                'purchase_date' => $data['purchase_date'],
                'purchase_number' => $data['purchase_number'],
                'status' => 'pending',
                'total_amount' => 0
            ]);
            
            $total = 0;
            foreach ($data['lines'] as $line) {
                $item = \App\Models\Item::findOrFail($line['item_id']);
                $unitPrice = $item->price;
                $subtotal = $line['quantity'] * $unitPrice;
                $total += $subtotal;
                $purchase->lines()->create([
                    'item_id' => $line['item_id'],
                    'quantity' => $line['quantity'],
                    'unit_price' => $unitPrice,
                    'subtotal' => $subtotal
                ]);
            }
            $purchase->update(['total_amount' => $total]);
            return response()->json($purchase->load('lines'), 201);
        });
    }
}