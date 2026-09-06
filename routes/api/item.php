<?php

use App\Http\Controllers\v1\ItemController;
use Illuminate\Support\Facades\Route;

Route::post('items/{id}/restore', [ItemController::class, 'restore'])->name('items.restore');
Route::apiResource('items', ItemController::class);
