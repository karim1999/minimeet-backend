<?php

use Illuminate\Support\Facades\Route;

foreach (config('tenancy.central_domains') as $domain) {
    Route::domain($domain)->group(function () {
        Route::get('/', function () {
            return view('welcome');
        });

        // Admin Dashboard Routes
        Route::prefix('admin')->name('admin.')->group(function () {
            // Public admin login
            Route::get('login', [App\Http\Controllers\Central\AdminAuthController::class, 'showLogin'])->name('login');
            Route::post('login', [App\Http\Controllers\Central\AdminAuthController::class, 'login']);

            // Protected admin routes
            Route::middleware(['central_admin_auth'])->group(function () {
                Route::post('logout', [App\Http\Controllers\Central\AdminAuthController::class, 'logout'])->name('logout');
                Route::get('dashboard', [App\Http\Controllers\Central\AdminDashboardController::class, 'index'])->name('dashboard');

                // Tenant User Management
                Route::prefix('tenant-users')->name('tenant-users.')->group(function () {
                    Route::get('/', [App\Http\Controllers\Central\TenantUserManagementController::class, 'index'])->name('index');
                    Route::get('/create', [App\Http\Controllers\Central\TenantUserManagementController::class, 'create'])->name('create');
                    Route::post('/', [App\Http\Controllers\Central\TenantUserManagementController::class, 'store'])->name('store');
                    Route::get('/{id}', [App\Http\Controllers\Central\TenantUserManagementController::class, 'show'])->name('show');
                    Route::get('/{id}/edit', [App\Http\Controllers\Central\TenantUserManagementController::class, 'edit'])->name('edit');
                    Route::put('/{id}', [App\Http\Controllers\Central\TenantUserManagementController::class, 'update'])->name('update');
                    Route::delete('/{id}', [App\Http\Controllers\Central\TenantUserManagementController::class, 'destroy'])->name('destroy');
                    Route::post('/{id}/toggle-status', [App\Http\Controllers\Central\TenantUserManagementController::class, 'toggleStatus'])->name('toggle-status');
                    Route::get('/{id}/activity', [App\Http\Controllers\Central\TenantUserManagementController::class, 'activity'])->name('activity');
                });

                // Super Admin only routes
                Route::middleware('super_admin_only')->group(function () {
                    Route::get('system-stats', [App\Http\Controllers\Central\AdminDashboardController::class, 'systemStats'])->name('system-stats');
                });
            });
        });
    });
}
