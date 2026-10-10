<?php
namespace App\Http\Controllers\Item;
use App\Http\Controllers\Controller;
use App\Models\Item;

class GetItemsController extends Controller {
    public function __invoke() {
        $this->authorize('viewAny', Item::class);
        return response()->json(Item::all());
    }
}