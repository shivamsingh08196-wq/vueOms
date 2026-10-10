<?php
namespace App\Http\Controllers\Purchase;
use App\Http\Controllers\Controller;
use App\Models\Purchase;

class GetPurchasesController extends Controller {
    public function __invoke() {
        $this->authorize('viewAny', Purchase::class);
        return response()->json(Purchase::all());
    }
}