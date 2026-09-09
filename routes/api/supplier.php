<?php

use App\Http\Controllers\v1\SupplierController;
use Illuminate\Support\Facades\Route;

Route::apiResource('suppliers', SupplierController::class);
