<?php
namespace App\Http\Controllers\Item;
use App\Http\Controllers\Controller;
use App\Models\Item;
use Illuminate\Http\Request;

class CreateItemController extends Controller {
    public function __invoke(Request $request) {
        $this->authorize('create', Item::class);
        $data = $request->all();
        $record = Item::create($data);
        return response()->json($record, 201);
    }
}