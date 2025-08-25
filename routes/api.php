<?php

use App\Http\Controllers\Central\AdminAuthController;
use App\Http\Controllers\Central\AdminDashboardController;
use App\Http\Controllers\Central\AuthController as CentralAuthController;
use App\Http\Controllers\Central\RegisterController as CentralRegisterController;
use App\Http\Controllers\Central\TenantUserManagementController;
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

                // Admin dashboard routes
                Route::prefix('admin')->middleware('central_admin_auth')->group(function () {
                    Route::post('login', [AdminAuthController::class, 'login'])->withoutMiddleware('central_admin_auth');
                    Route::post('logout', [AdminAuthController::class, 'logout']);
                    Route::get('dashboard', [AdminDashboardController::class, 'dashboard']);
                    Route::get('health', [AdminDashboardController::class, 'health']);
                    Route::get('activities', [AdminDashboardController::class, 'activities']);
                    Route::get('metrics', [AdminDashboardController::class, 'metrics']);
                    Route::get('export', [AdminDashboardController::class, 'export']);

                    // Tenant User Management
                    Route::prefix('tenant-users')->group(function () {
                        Route::get('/', [TenantUserManagementController::class, 'index']);
                        Route::get('/{id}', [TenantUserManagementController::class, 'show']);
                        Route::post('/', [TenantUserManagementController::class, 'store']);
                        Route::put('/{id}', [TenantUserManagementController::class, 'update']);
                        Route::delete('/{id}', [TenantUserManagementController::class, 'destroy']);
                        Route::post('/{id}/toggle-status', [TenantUserManagementController::class, 'toggleStatus']);
                        Route::post('/{id}/reset-password', [TenantUserManagementController::class, 'resetPassword']);
                        Route::get('/{id}/activity', [TenantUserManagementController::class, 'activity']);
                    });

                    // Super Admin only routes
                    Route::middleware('super_admin_only')->group(function () {
                        Route::get('users', [AdminDashboardController::class, 'users']);
                        Route::get('system-stats', [AdminDashboardController::class, 'systemStats']);
                    });
                });
            });
        });
    });
}
