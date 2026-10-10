<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Client;

class GetClientController extends Controller {
    public function __invoke(Client $client) {
        $this->authorize('view', $client);
        return response()->json($client);
    }
}