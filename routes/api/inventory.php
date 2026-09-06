<?php

use App\Http\Controllers\v1\InventoryBatchController;
use App\Http\Controllers\v1\InventoryController;
use App\Http\Controllers\v1\StockController;
use App\Http\Controllers\v1\StockMovementController;
use Illuminate\Support\Facades\Route;

/*
| Stock movement routes live here rather than in their own file, and must stay
| ABOVE apiResource. routes/api.php loads this directory with glob(), which
| returns files alphabetically, so a separate file could never be guaranteed to
| register before `inventories/{inventory}` and would silently swallow a literal
| path segment like `low-stock` as an id. Same reasoning as routes/api/appointment.php.
*/
Route::post('inventories/stock-in', [StockController::class, 'stockIn'])
    ->name('inventories.stock-in');

Route::post('inventories/usage', [StockController::class, 'usage'])
    ->name('inventories.usage');

Route::post('inventories/adjust', [StockController::class, 'adjust'])
    ->name('inventories.adjust');

Route::post('inventories/transfer', [StockController::class, 'transfer'])
    ->name('inventories.transfer');

Route::post('inventories/writeoff', [StockController::class, 'writeoff'])
    ->name('inventories.writeoff');

Route::apiResource('inventories', InventoryController::class);

// The ledger. Read-only — StockLedger is the only writer.
Route::get('stock-movements', [StockMovementController::class, 'index'])
    ->name('stock-movements.index');

// The lots behind the stock. Read-only for the same reason.
Route::get('inventory-batches', [InventoryBatchController::class, 'index'])
    ->name('inventory-batches.index');
