<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class CreateClientController extends Controller {
    public function __invoke(Request $request) {
        $this->authorize('create', Client::class);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:clients,email',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);
        $record = Client::create($data);
        return response()->json($record, 201);
    }
}