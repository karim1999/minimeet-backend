<?php

use App\Http\Controllers\Central\AuthController as CentralAuthController;
use App\Http\Controllers\Central\RegisterController as CentralRegisterController;
use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        // Central API routes
        Route::prefix('v1')->group(function () {
            // Public auth routes with rate limiting
            Route::middleware(['throttle:5,1'])->group(function () {
                Route::post('register', [CentralRegisterController::class, 'register']);
                Route::post('login', [CentralAuthController::class, 'login']);
            });

            // Protected routes (session or token auth) with API rate limiting
            Route::middleware(['auth:web,central_sanctum', 'throttle:100,1'])->group(function () {
                Route::post('logout', [CentralAuthController::class, 'logout']);
                Route::get('user', [CentralAuthController::class, 'user']);

                // Token management endpoints
                Route::prefix('tokens')->group(function () {
                    Route::get('/', [CentralAuthController::class, 'listTokens']);
                    Route::post('create', [CentralAuthController::class, 'createToken']);
                    Route::post('revoke', [CentralAuthController::class, 'revokeToken']);
                    Route::post('revoke-all', [CentralAuthController::class, 'revokeAllTokens']);
                    Route::post('revoke-current', [CentralAuthController::class, 'revokeCurrentToken']);
                });
            });
        });
    });
}
