<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantUserAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verify we are in tenant context
        if (! tenant()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Tenant context required.'], 400);
            }
            abort(400, 'Tenant context required.');
        }

        // Check if user is authenticated using tenant guard
        $guard = $request->expectsJson() ? 'sanctum' : 'tenant_web';

        if (! Auth::guard($guard)->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('tenant.auth.login');
        }

        $user = Auth::guard($guard)->user();

        // Check if user is active
        if (! $user->is_active) {
            Auth::guard($guard)->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Account suspended.'], 403);
            }

            return redirect()->route('tenant.auth.login')
                ->withErrors(['error' => 'Your account has been suspended. Please contact your administrator.']);
        }

        // Verify user belongs to current tenant (additional security check)
        if ($user->getConnectionName() !== tenancy()->database()->getName()) {
            Auth::guard($guard)->logout();

            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized tenant access.'], 403);
            }

            return redirect()->route('tenant.auth.login')
                ->withErrors(['error' => 'Unauthorized tenant access.']);
        }

        return $next($request);
    }
}
