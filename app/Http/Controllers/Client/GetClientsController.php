<?php
namespace App\Http\Controllers\Client;
use App\Http\Controllers\Controller;
use App\Models\Client;

class GetClientsController extends Controller {
    public function __invoke() {
        $this->authorize('viewAny', Client::class);
        return response()->json(Client::all());
    }
}