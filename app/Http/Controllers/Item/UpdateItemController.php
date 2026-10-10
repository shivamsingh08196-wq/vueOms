<?php
namespace App\Http\Controllers\Item;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class UpdateItemController extends Controller {
    public function __invoke(Request $request, Item $item) {
        $this->authorize('update', $item);
        $item->update($request->all());
        return response()->json($item);
    }
}