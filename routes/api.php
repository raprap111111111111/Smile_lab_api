<?php

use App\Http\Controllers\Api\GalleryController;
use App\Http\Controllers\Api\ServiceController;
use App\Http\Controllers\v1\AuthController;
use App\Http\Controllers\v1\HealthController;
use App\Http\Controllers\v1\SettingController;
use App\Http\Controllers\v1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API V1
|--------------------------------------------------------------------------
*/

Route::prefix('v1')->group(function () {
    Route::get('health', HealthController::class)->middleware('throttle:60,1');

    /*
    |------------------------
    | AUTH (Public)
    |------------------------
    */
    Route::prefix('auth')->group(function () {
        Route::post('login',    [AuthController::class, 'login']);
        Route::post('register', [AuthController::class, 'register']);
        Route::post('refresh',  [AuthController::class, 'refresh']);
        Route::post('social',   [AuthController::class, 'socialLogin']);
    });

    /*
    |------------------------
    | AUTH (Protected)
    |------------------------
    */
    Route::middleware('auth:api')->group(function () {

        Route::prefix('auth')->group(function () {
            Route::post('logout',          [AuthController::class, 'logout']);
            Route::post('update-password', [AuthController::class, 'updatePassword']);
        });

        Route::get('profile',   [AuthController::class, 'profile']);
        Route::get('users/me', [UserController::class, 'me'])->name('users.me');

        /*
        |------------------------
        | AUTO-LOAD MODULES
        |------------------------
        */
        foreach (glob(__DIR__ . '/api/*.php') as $routeFile) {
            require $routeFile;
        }
    });
});

/*
|--------------------------------------------------------------------------
| PUBLIC (No Auth)
|--------------------------------------------------------------------------
*/
Route::prefix('public')->group(function () {
    Route::get('/services', [ServiceController::class, 'publicIndex']);
    Route::get('/gallery',  [GalleryController::class, 'publicIndex']);
    Route::get('/settings', [SettingController::class, 'publicIndex']);
});

/*
|--------------------------------------------------------------------------
| ADMIN (Passport Protected)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:api')->group(function () {
    Route::apiResource('services', ServiceController::class);
    Route::apiResource('gallery',  GalleryController::class);
    Route::post('/gallery/bulk-delete', [GalleryController::class, 'bulkDelete']);
});
