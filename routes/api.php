<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Lalalili\ShoppingCart\Http\Controllers\CartApiController;

Route::get('/', [CartApiController::class, 'show']);
Route::post('/items', [CartApiController::class, 'add']);
Route::post('/items/batch', [CartApiController::class, 'addMany']);
Route::patch('/items/{id}', [CartApiController::class, 'update']);
Route::delete('/items/{id}', [CartApiController::class, 'remove']);
Route::delete('/items', [CartApiController::class, 'removeMany']);
Route::post('/conditions', [CartApiController::class, 'condition']);
Route::put('/context', [CartApiController::class, 'context']);
Route::delete('/', [CartApiController::class, 'clear']);
