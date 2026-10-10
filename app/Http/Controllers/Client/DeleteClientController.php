<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Client;

class DeleteClientController extends Controller {
    public function __invoke(Client $client) {
        $this->authorize('delete', $client);
        $client->delete();
        return response()->json(null, 204);
    }
}