<?php

namespace App\Http\Middleware\Tenant;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class TenantRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        $guard = $request->expectsJson() ? 'sanctum' : 'tenant_web';
        $user = Auth::guard($guard)->user();

        if (! $user) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('tenant.auth.login');
        }

        // Check if user has any of the required roles
        if (! in_array($user->role, $roles)) {
            $requiredRoles = implode(', ', $roles);

            // Log permission check for audit
            $user->logActivity('permission_denied', null, [
                'required_roles' => $roles,
                'user_role' => $user->role,
                'route' => $request->route()?->getName(),
            ]);

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Insufficient permissions.',
                    'required_roles' => $roles,
                    'user_role' => $user->role,
                ], 403);
            }

            abort(403, "Access denied. Required roles: {$requiredRoles}");
        }

        return $next($request);
    }
}
