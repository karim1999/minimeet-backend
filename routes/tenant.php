<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use Stancl\Tenancy\Middleware\InitializeTenancyByDomain;
use Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here you can register the tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider.
|
| Feel free to customize them however you want. Good luck!
|
*/

Route::middleware([
    'api',
    InitializeTenancyByDomain::class,
    PreventAccessFromCentralDomains::class,
])->group(function () {
    Route::get('/', function () {
        return view('tenant.welcome');
    });

    // Public auth routes with rate limiting
    Route::prefix('auth')->middleware(['throttle:5,1'])->group(function () {
        Route::post('register', [\App\Http\Controllers\Tenant\AuthController::class, 'register']);
        Route::post('login', [\App\Http\Controllers\Tenant\AuthController::class, 'login']);
        Route::post('forgot-password', [\App\Http\Controllers\Tenant\AuthController::class, 'forgotPassword'])->name('password.email');
        Route::post('reset-password', [\App\Http\Controllers\Tenant\AuthController::class, 'resetPassword'])->name('password.reset');
    });

    // Protected tenant routes
    Route::middleware(['auth:tenant_sanctum', 'throttle:100,1'])->group(function () {
        Route::post('auth/logout', [\App\Http\Controllers\Tenant\AuthController::class, 'logout']);
        Route::get('auth/user', [\App\Http\Controllers\Tenant\AuthController::class, 'user']);

        // User management pages (web interface)
        Route::get('users', function () {
            return view('tenant.users.index');
        });

        // User management API routes
        Route::prefix('users')->group(function () {
            Route::get('/', [\App\Http\Controllers\Tenant\UserController::class, 'index']);
            Route::get('/me', [\App\Http\Controllers\Tenant\UserController::class, 'profile']);
            Route::put('/me', [\App\Http\Controllers\Tenant\UserController::class, 'updateProfile']);
            Route::post('/change-password', [\App\Http\Controllers\Tenant\UserController::class, 'changePassword']);

            // Admin-only user management
            Route::middleware('tenant_role:admin')->group(function () {
                Route::get('/{id}', [\App\Http\Controllers\Tenant\UserController::class, 'show']);
                Route::post('/', [\App\Http\Controllers\Tenant\UserController::class, 'store']);
                Route::put('/{id}', [\App\Http\Controllers\Tenant\UserController::class, 'update']);
                Route::delete('/{id}', [\App\Http\Controllers\Tenant\UserController::class, 'destroy']);
                Route::post('/{id}/toggle-status', [\App\Http\Controllers\Tenant\UserController::class, 'toggleStatus']);
                Route::get('/{id}/activity', [\App\Http\Controllers\Tenant\UserController::class, 'activity']);
            });
        });
    });
});
