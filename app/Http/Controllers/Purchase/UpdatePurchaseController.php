<?php
namespace App\Http\Controllers\Purchase;
use App\Http\Controllers\Controller;
use App\Models\Purchase;
use Illuminate\Http\Request;

class UpdatePurchaseController extends Controller {
    public function __invoke(Request $request, Purchase $purchase) {
        $this->authorize('update', $purchase);
        $purchase->update($request->all());
        return response()->json($purchase);
    }
}