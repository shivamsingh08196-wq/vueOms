<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MeController;

Route::get('/health', fn () => response()->json(['status' => 'ok']));
Route::post('/login', LoginController::class);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', LogoutController::class);
    Route::get('/me', MeController::class);

    // Clients
    Route::get('/clients', App\Http\Controllers\Client\GetClientsController::class);
    Route::get('/clients/{client}', App\Http\Controllers\Client\GetClientController::class);
    Route::post('/clients', App\Http\Controllers\Client\CreateClientController::class);
    Route::put('/clients/{client}', App\Http\Controllers\Client\UpdateClientController::class);
    Route::delete('/clients/{client}', App\Http\Controllers\Client\DeleteClientController::class);

    // Items
    Route::get('/items', App\Http\Controllers\Item\GetItemsController::class);
    Route::get('/items/{item}', App\Http\Controllers\Item\GetItemController::class);
    Route::post('/items', App\Http\Controllers\Item\CreateItemController::class);
    Route::put('/items/{item}', App\Http\Controllers\Item\UpdateItemController::class);
    Route::delete('/items/{item}', App\Http\Controllers\Item\DeleteItemController::class);

    // Purchases
    Route::get('/purchases', App\Http\Controllers\Purchase\GetPurchasesController::class);
    Route::get('/purchases/{purchase}', App\Http\Controllers\Purchase\GetPurchaseController::class);
    Route::post('/purchases', App\Http\Controllers\Purchase\CreatePurchaseController::class);
    Route::put('/purchases/{purchase}', App\Http\Controllers\Purchase\UpdatePurchaseController::class);
    Route::delete('/purchases/{purchase}', App\Http\Controllers\Purchase\DeletePurchaseController::class);
});
