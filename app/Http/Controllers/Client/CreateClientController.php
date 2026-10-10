<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class CreateClientController extends Controller {
    public function __invoke(Request $request) {
        $this->authorize('create', Client::class);
        $data = $request->all();
        $record = Client::create($data);
        return response()->json($record, 201);
    }
}