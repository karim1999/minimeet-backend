<?php

namespace App\Services\Central;

use App\Logging\Central\AuthenticationLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SessionService
{
    public function __construct(
        private readonly AuthenticationLogger $authLogger
    ) {}

    /**
     * Regenerate the session for security.
     */
    public function regenerateSession(Request $request): void
    {
        // Only regenerate session if it exists (not in testing without middleware)
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }
    }

    /**
     * Invalidate the current session.
     */
    public function invalidateSession(Request $request): void
    {
        $user = $request->user();

        Auth::guard('web')->logout();

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        // Log the logout event
        if ($user) {
            $this->authLogger->logLogout($user, $request, 'session');
        }
    }

    /**
     * Get session information.
     */
    public function getSessionInfo(Request $request): array
    {
        if (! $request->hasSession()) {
            return [
                'has_session' => false,
                'session_active' => false,
            ];
        }

        $session = $request->session();

        return [
            'has_session' => true,
            'session_active' => Auth::guard('web')->check(),
            'session_id' => $session->getId(),
            'session_lifetime' => config('session.lifetime'),
            'csrf_token' => $session->token(),
            'last_activity' => $session->get('_previous.timestamp', now()->timestamp),
        ];
    }

    /**
     * Check if session is valid and active.
     */
    public function isSessionValid(Request $request): bool
    {
        if (! $request->hasSession()) {
            return false;
        }

        return Auth::guard('web')->check();
    }

    /**
     * Store data in the session.
     */
    public function storeInSession(Request $request, string $key, mixed $value): void
    {
        if ($request->hasSession()) {
            $request->session()->put($key, $value);
        }
    }

    /**
     * Retrieve data from the session.
     */
    public function getFromSession(Request $request, string $key, mixed $default = null): mixed
    {
        if (! $request->hasSession()) {
            return $default;
        }

        return $request->session()->get($key, $default);
    }

    /**
     * Remove data from the session.
     */
    public function removeFromSession(Request $request, string $key): void
    {
        if ($request->hasSession()) {
            $request->session()->forget($key);
        }
    }

    /**
     * Flash data to the session for the next request.
     */
    public function flashToSession(Request $request, string $key, mixed $value): void
    {
        if ($request->hasSession()) {
            $request->session()->flash($key, $value);
        }
    }

    /**
     * Get session configuration information.
     */
    public function getSessionConfiguration(): array
    {
        return [
            'driver' => config('session.driver'),
            'lifetime' => config('session.lifetime'),
            'encrypt' => config('session.encrypt'),
            'secure' => config('session.secure'),
            'same_site' => config('session.same_site'),
            'domain' => config('session.domain'),
        ];
    }

    /**
     * Migrate session to new ID (useful for security).
     */
    public function migrateSession(Request $request): string
    {
        if (! $request->hasSession()) {
            return '';
        }

        $oldId = $request->session()->getId();
        $request->session()->migrate();
        $newId = $request->session()->getId();

        return $newId;
    }

    /**
     * Perform session cleanup and security checks.
     */
    public function performSecurityCheck(Request $request): array
    {
        $results = [
            'security_check_passed' => true,
            'issues' => [],
            'session_info' => $this->getSessionInfo($request),
        ];

        // Check for suspicious session activity
        if ($request->hasSession()) {
            $session = $request->session();

            // Check session age
            $sessionAge = now()->timestamp - ($session->get('_previous.timestamp', now()->timestamp));
            $maxAge = config('session.lifetime') * 60; // Convert minutes to seconds

            if ($sessionAge > $maxAge) {
                $results['issues'][] = 'Session has exceeded maximum age';
                $results['security_check_passed'] = false;
            }

            // Check for session hijacking indicators
            $storedUserAgent = $session->get('_user_agent');
            $currentUserAgent = $request->userAgent();

            if ($storedUserAgent && $storedUserAgent !== $currentUserAgent) {
                $results['issues'][] = 'User agent mismatch detected';
                $results['security_check_passed'] = false;
            }

            // Store current user agent for future checks
            if (! $storedUserAgent) {
                $session->put('_user_agent', $currentUserAgent);
            }
        }

        return $results;
    }
}
