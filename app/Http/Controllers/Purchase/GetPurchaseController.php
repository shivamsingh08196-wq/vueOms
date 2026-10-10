<?php
namespace App\Http\Controllers\Purchase;
use App\Http\Controllers\Controller;
use App\Models\Purchase;

class GetPurchaseController extends Controller {
    public function __invoke(Purchase $purchase) {
        $this->authorize('view', $purchase);
        return response()->json($purchase);
    }
}