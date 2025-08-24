<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Central\AuthController as CentralAuthController;
use App\Http\Controllers\Central\RegisterController as CentralRegisterController;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        // Central API routes
        Route::prefix('v1')->group(function () {
            // Public auth routes
            Route::post('register', [CentralRegisterController::class, 'register']);
            Route::post('login', [CentralAuthController::class, 'login']);

            // Protected routes (session auth)
            Route::middleware(['auth:web'])->group(function () {
                Route::post('logout', [CentralAuthController::class, 'logout']);
                Route::get('user', [CentralAuthController::class, 'user']);
            });
        });
    });
}
