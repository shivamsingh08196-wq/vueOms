<?php
namespace App\Http\Controllers\Item;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class CreateItemController extends Controller {
    public function __invoke(Request $request) {
        $this->authorize('create', Item::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
        ]);
        $record = Item::create($data);
        return response()->json($record, 201);
    }
}