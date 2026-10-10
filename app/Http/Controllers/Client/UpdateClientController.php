<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Client;
use Illuminate\Http\Request;

class UpdateClientController extends Controller {
    public function __invoke(Request $request, Client $client) {
        $this->authorize('update', $client);
        $client->update($request->all());
        return response()->json($client);
    }
}