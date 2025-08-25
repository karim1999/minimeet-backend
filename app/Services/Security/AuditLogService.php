<?php

declare(strict_types=1);

namespace App\Services\Security;

use App\Models\Central\CentralUser;
use App\Models\Central\CentralUserActivity;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class AuditLogService
{
    /**
     * Log a security-related activity.
     */
    public function logSecurityEvent(
        ?Model $user,
        string $action,
        string $description,
        array $context = [],
        string $severity = 'info',
        string $ipAddress = '',
        string $userAgent = ''
    ): void {
        $logData = [
            'action' => $action,
            'description' => $description,
            'severity' => $severity,
            'ip_address' => $ipAddress ?: request()->ip() ?: '127.0.0.1',
            'user_agent' => $userAgent ?: request()->userAgent() ?: 'Unknown',
            'context' => $context,
            'timestamp' => now()->toISOString(),
        ];

        // Add user information if available
        if ($user) {
            $logData['user_id'] = $user->getKey();
            $logData['user_email'] = $user->email ?? 'N/A';
            $logData['user_type'] = $this->getUserType($user);

            if ($user instanceof CentralUser || $user instanceof TenantUser) {
                $logData['user_role'] = $user->role;
            }
        } else {
            $logData['user_type'] = 'anonymous';
        }

        // Add tenant context if available
        if (tenancy()->initialized) {
            $logData['tenant_id'] = tenant('id');
        }

        // Log to Laravel's logging system
        Log::channel('security')->log($severity, $description, $logData);

        // Store in database for long-term retention and querying
        $this->storeInDatabase($user, $logData);

        // Trigger alerts for critical security events
        if (in_array($severity, ['critical', 'emergency']) || $this->isCriticalAction($action)) {
            $this->triggerSecurityAlert($logData);
        }
    }

    /**
     * Log authentication-related events.
     */
    public function logAuthEvent(
        ?Model $user,
        string $action,
        bool $success,
        array $additionalContext = []
    ): void {
        $severity = $success ? 'info' : 'warning';
        $description = $this->getAuthDescription($action, $success);

        $context = array_merge($additionalContext, [
            'success' => $success,
            'auth_action' => $action,
        ]);

        $this->logSecurityEvent($user, $action, $description, $context, $severity);
    }

    /**
     * Log permission/authorization events.
     */
    public function logAuthorizationEvent(
        ?Model $user,
        string $resource,
        string $action,
        bool $granted,
        array $additionalContext = []
    ): void {
        $severity = $granted ? 'info' : 'warning';
        $description = sprintf(
            'Access %s to %s %s',
            $granted ? 'granted' : 'denied',
            $action,
            $resource
        );

        $context = array_merge($additionalContext, [
            'resource' => $resource,
            'permission_action' => $action,
            'granted' => $granted,
        ]);

        $this->logSecurityEvent(
            $user,
            'authorization_check',
            $description,
            $context,
            $severity
        );
    }

    /**
     * Log data access events.
     */
    public function logDataAccess(
        ?Model $user,
        string $dataType,
        string $action,
        array $identifiers = [],
        array $additionalContext = []
    ): void {
        $description = sprintf(
            'Data access: %s %s',
            $action,
            $dataType
        );

        $context = array_merge($additionalContext, [
            'data_type' => $dataType,
            'data_action' => $action,
            'identifiers' => $identifiers,
        ]);

        $this->logSecurityEvent($user, 'data_access', $description, $context);
    }

    /**
     * Log configuration changes.
     */
    public function logConfigChange(
        Model $user,
        string $configType,
        array $changes,
        array $additionalContext = []
    ): void {
        $description = sprintf('Configuration changed: %s', $configType);

        $context = array_merge($additionalContext, [
            'config_type' => $configType,
            'changes' => $changes,
        ]);

        $this->logSecurityEvent(
            $user,
            'config_change',
            $description,
            $context,
            'notice'
        );
    }

    /**
     * Log suspicious activities.
     */
    public function logSuspiciousActivity(
        ?Model $user,
        string $activityType,
        string $description,
        array $evidence = []
    ): void {
        $context = [
            'activity_type' => $activityType,
            'evidence' => $evidence,
            'detected_at' => now()->toISOString(),
        ];

        $this->logSecurityEvent(
            $user,
            'suspicious_activity',
            $description,
            $context,
            'warning'
        );

        // Always trigger alerts for suspicious activities
        $this->triggerSecurityAlert([
            'action' => 'suspicious_activity',
            'description' => $description,
            'user' => $user ? $user->email : 'Anonymous',
            'context' => $context,
        ]);
    }

    /**
     * Log failed security events (rate limiting, brute force, etc.).
     */
    public function logSecurityViolation(
        ?Model $user,
        string $violationType,
        string $description,
        array $details = []
    ): void {
        $context = [
            'violation_type' => $violationType,
            'details' => $details,
        ];

        $this->logSecurityEvent(
            $user,
            'security_violation',
            $description,
            $context,
            'error'
        );
    }

    /**
     * Get audit trail for a specific user.
     */
    public function getUserAuditTrail(Model $user, int $days = 30): array
    {
        if ($user instanceof CentralUser) {
            $activities = CentralUserActivity::where('central_user_id', $user->id)
                ->where('created_at', '>=', now()->subDays($days))
                ->orderBy('created_at', 'desc')
                ->get();
        } elseif ($user instanceof TenantUser) {
            $activities = TenantUserActivity::where('user_id', $user->id)
                ->where('created_at', '>=', now()->subDays($days))
                ->orderBy('created_at', 'desc')
                ->get();
        } else {
            return [];
        }

        return $activities->map(function ($activity) {
            return [
                'id' => $activity->id,
                'action' => $activity->action,
                'description' => $activity->description,
                'ip_address' => $activity->ip_address,
                'user_agent' => $activity->user_agent,
                'metadata' => $activity->metadata,
                'created_at' => $activity->created_at->toISOString(),
            ];
        })->toArray();
    }

    /**
     * Get security events for a specific timeframe.
     */
    public function getSecurityEvents(
        int $hours = 24,
        array $severities = ['warning', 'error', 'critical', 'emergency']
    ): array {
        $events = [];

        // Get Central events
        $centralEvents = CentralUserActivity::whereIn('action', [
            'login_failed',
            'security_violation',
            'suspicious_activity',
            'authorization_denied',
        ])
            ->where('created_at', '>=', now()->subHours($hours))
            ->orderBy('created_at', 'desc')
            ->get();

        foreach ($centralEvents as $event) {
            $events[] = [
                'id' => $event->id,
                'type' => 'central',
                'action' => $event->action,
                'description' => $event->description,
                'user_id' => $event->central_user_id,
                'ip_address' => $event->ip_address,
                'created_at' => $event->created_at->toISOString(),
                'metadata' => $event->metadata,
            ];
        }

        // If we're in a tenant context, get tenant events too
        if (tenancy()->initialized) {
            $tenantEvents = TenantUserActivity::whereIn('action', [
                'login_failed',
                'security_violation',
                'suspicious_activity',
                'authorization_denied',
            ])
                ->where('created_at', '>=', now()->subHours($hours))
                ->orderBy('created_at', 'desc')
                ->get();

            foreach ($tenantEvents as $event) {
                $events[] = [
                    'id' => $event->id,
                    'type' => 'tenant',
                    'tenant_id' => tenant('id'),
                    'action' => $event->action,
                    'description' => $event->description,
                    'user_id' => $event->user_id,
                    'ip_address' => $event->ip_address,
                    'created_at' => $event->created_at->toISOString(),
                    'metadata' => $event->metadata,
                ];
            }
        }

        // Sort by timestamp (most recent first)
        usort($events, fn ($a, $b) => strtotime($b['created_at']) - strtotime($a['created_at']));

        return $events;
    }

    /**
     * Store audit log in database.
     */
    private function storeInDatabase(?Model $user, array $logData): void
    {
        try {
            if ($user instanceof CentralUser) {
                CentralUserActivity::create([
                    'user_id' => $user->id,
                    'action' => $logData['action'],
                    'ip_address' => $logData['ip_address'],
                    'user_agent' => $logData['user_agent'],
                    'metadata' => [
                        'severity' => $logData['severity'],
                        'context' => $logData['context'],
                        'timestamp' => $logData['timestamp'],
                        'description' => $logData['description'],
                    ],
                ]);
            } elseif ($user instanceof TenantUser) {
                TenantUserActivity::create([
                    'user_id' => $user->id,
                    'action' => $logData['action'],
                    'description' => $logData['description'],
                    'ip_address' => $logData['ip_address'],
                    'user_agent' => $logData['user_agent'],
                    'metadata' => [
                        'severity' => $logData['severity'],
                        'context' => $logData['context'],
                        'timestamp' => $logData['timestamp'],
                    ],
                ]);
            } else {
                // Skip database storage for anonymous events to avoid user_id constraint issues
                // They are still logged to the file system above
                Log::debug('Skipping database storage for anonymous audit event', [
                    'action' => $logData['action'],
                    'ip_address' => $logData['ip_address'],
                ]);
            }
        } catch (\Exception $e) {
            Log::error('Failed to store audit log in database', [
                'error' => $e->getMessage(),
                'audit_data' => $logData,
            ]);
        }
    }

    /**
     * Trigger security alerts for critical events.
     */
    private function triggerSecurityAlert(array $alertData): void
    {
        // In a real implementation, this would:
        // - Send emails to security team
        // - Post to Slack/Teams channels
        // - Create tickets in monitoring systems
        // - Trigger SIEM rules

        Log::channel('security')->critical('SECURITY ALERT', $alertData);

        // Example: Queue an alert notification job
        // SecurityAlertJob::dispatch($alertData);
    }

    /**
     * Check if an action is considered critical.
     */
    private function isCriticalAction(string $action): bool
    {
        $criticalActions = [
            'user_deleted',
            'role_escalation',
            'security_settings_changed',
            'data_export',
            'bulk_operation',
            'suspicious_activity',
            'brute_force_detected',
            'account_takeover_attempt',
        ];

        return in_array($action, $criticalActions);
    }

    /**
     * Get description for authentication events.
     */
    private function getAuthDescription(string $action, bool $success): string
    {
        $status = $success ? 'successful' : 'failed';

        return match ($action) {
            'login' => "Login attempt {$status}",
            'logout' => "Logout {$status}",
            'password_reset' => "Password reset {$status}",
            '2fa_verification' => "2FA verification {$status}",
            'token_refresh' => "Token refresh {$status}",
            default => "Authentication action '{$action}' {$status}",
        };
    }

    /**
     * Get user type from model.
     */
    private function getUserType(Model $user): string
    {
        if ($user instanceof CentralUser) {
            return 'central_user';
        }

        if ($user instanceof TenantUser) {
            return 'tenant_user';
        }

        return get_class($user);
    }
}
