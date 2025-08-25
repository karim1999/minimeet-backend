<?php

namespace App\Http\Middleware\Central;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class SuperAdminOnly
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Determine the appropriate guard based on request type
        $guard = $request->expectsJson() ? 'central_sanctum' : 'web';
        $user = Auth::guard($guard)->user();

        // Verify user is a super admin
        if (! $user || ! $user->isSuperAdmin()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Super admin access required.'], 403);
            }

            abort(403, 'Super admin access required.');
        }

        // Log sensitive access
        $user->logActivity('super_admin_access', null, [
            'route' => $request->route()?->getName(),
            'url' => $request->fullUrl(),
            'method' => $request->method(),
        ]);

        return $next($request);
    }
}
