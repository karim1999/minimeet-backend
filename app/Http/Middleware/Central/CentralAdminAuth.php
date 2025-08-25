<?php

namespace App\Http\Middleware\Central;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CentralAdminAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Determine the appropriate guard based on request path or type
        // API routes should always use Sanctum guard
        $guard = ($request->is('api/*') || $request->expectsJson()) ? 'central_sanctum' : 'web';

        // Check if user is authenticated using the appropriate guard
        if (! Auth::guard($guard)->check()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }

            return redirect()->route('admin.login');
        }

        $user = Auth::guard($guard)->user();

        // Verify user is a central admin
        if (! $user->is_central || ! in_array($user->role, ['super_admin', 'admin', 'support'])) {
            // Only logout for session-based auth, Sanctum doesn't have logout method
            if ($guard === 'web') {
                Auth::guard($guard)->logout();
            }

            if ($request->is('api/*') || $request->expectsJson()) {
                return response()->json(['message' => 'Unauthorized access.'], 403);
            }

            return redirect()->route('admin.login')
                ->withErrors(['error' => 'Unauthorized access. Central admin privileges required.']);
        }

        // Log access for audit trail (only for successful authentications)
        try {
            $user->logActivity('admin_access', null, [
                'route' => $request->route()?->getName(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
        } catch (\Exception $e) {
            // Don't fail the request if logging fails
            \Log::warning('Failed to log admin access activity', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);
        }

        return $next($request);
    }
}
