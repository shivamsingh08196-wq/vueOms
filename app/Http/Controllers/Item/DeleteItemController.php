<?php
namespace App\Http\Controllers\Item;
use App\Http\Controllers\Controller;
use App\Models\Item;

class DeleteItemController extends Controller {
    public function __invoke(Item $item) {
        $this->authorize('delete', $item);
        $item->delete();
        return response()->json(null, 204);
    }
}