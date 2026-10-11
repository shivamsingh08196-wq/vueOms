<?php
namespace App\Http\Controllers\Item;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class UpdateItemController extends Controller {
    public function __invoke(Request $request, Item $item) {
        $this->authorize('update', $item);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'sometimes|required|numeric|min:0',
            'stock' => 'sometimes|required|integer|min:0',
        ]);
        $item->update($data);
        return response()->json($item);
    }
}