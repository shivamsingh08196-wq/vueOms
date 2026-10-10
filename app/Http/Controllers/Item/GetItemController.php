<?php
namespace App\Http\Controllers\Item;
use App\Http\Controllers\Controller;
use App\Models\Item;

class GetItemController extends Controller {
    public function __invoke(Item $item) {
        $this->authorize('view', $item);
        return response()->json($item);
    }
}