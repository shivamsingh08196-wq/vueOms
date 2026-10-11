<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class UpdateClientController extends Controller {
    public function __invoke(Request $request, Client $client) {
        $this->authorize('update', $client);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|unique:clients,email,' . $client->id,
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
        ]);
        $client->update($data);
        return response()->json($client);
    }
}