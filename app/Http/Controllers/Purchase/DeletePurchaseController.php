<?php
namespace App\Http\Controllers\Purchase;
use App\Http\Controllers\Controller;
use App\Models\Purchase;

class DeletePurchaseController extends Controller {
    public function __invoke(Purchase $purchase) {
        $this->authorize('delete', $purchase);
        $purchase->delete();
        return response()->json(null, 204);
    }
}