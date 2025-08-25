<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Register custom middleware aliases
        $middleware->alias([
            'central_admin_auth' => \App\Http\Middleware\Central\CentralAdminAuth::class,
            'super_admin_only' => \App\Http\Middleware\Central\SuperAdminOnly::class,
            'throttle_auth_attempts' => \App\Http\Middleware\Central\ThrottleAuthAttempts::class,
            'tenant_user_auth' => \App\Http\Middleware\Tenant\TenantUserAuth::class,
            'tenant_role' => \App\Http\Middleware\Tenant\TenantRole::class,
            'security' => \App\Http\Middleware\SecurityMiddleware::class,
        ]);

        // Apply security middleware to API routes
        $middleware->group('api', [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            'security',
        ]);

        // Add tenancy routing for tenant routes
        $middleware->group('tenant', [
            \Stancl\Tenancy\Middleware\InitializeTenancyByDomain::class,
            \Stancl\Tenancy\Middleware\PreventAccessFromCentralDomains::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
