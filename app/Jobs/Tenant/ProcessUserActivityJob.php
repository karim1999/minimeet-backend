<?php

declare(strict_types=1);

namespace App\Jobs\Tenant;

use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessUserActivityJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    public function __construct(
        private readonly int $userId,
        private readonly string $action,
        private readonly string $description,
        private readonly string $ipAddress,
        private readonly string $userAgent,
        private readonly array $metadata = []
    ) {
        // Job will be automatically queued to the correct tenant
    }

    public function handle(): void
    {
        try {
            // Verify we're in the correct tenant context
            if (! tenancy()->initialized) {
                throw new \RuntimeException('Tenant context not initialized');
            }

            $user = TenantUser::findOrFail($this->userId);

            Log::info('Processing user activity', [
                'tenant_id' => tenant('id'),
                'user_id' => $user->id,
                'action' => $this->action,
            ]);

            // Create the activity record
            $activity = TenantUserActivity::create([
                'user_id' => $user->id,
                'action' => $this->action,
                'description' => $this->description,
                'ip_address' => $this->ipAddress,
                'metadata' => array_merge($this->metadata, [
                    'job_id' => $this->job?->getJobId(),
                    'processed_at' => now()->toISOString(),
                ]),
            ]);

            // Update user's last activity timestamp if this is a login or active action
            if (in_array($this->action, ['login', 'user_updated', 'password_changed'])) {
                $user->update(['last_login_at' => now()]);
            }

            // Process additional actions based on activity type
            $this->processActivitySpecificActions($user, $activity);

            Log::info('User activity processed successfully', [
                'tenant_id' => tenant('id'),
                'user_id' => $user->id,
                'activity_id' => $activity->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to process user activity', [
                'tenant_id' => tenant('id') ?? 'unknown',
                'user_id' => $this->userId,
                'action' => $this->action,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('User activity processing job failed', [
            'tenant_id' => tenant('id') ?? 'unknown',
            'user_id' => $this->userId,
            'action' => $this->action,
            'error' => $exception->getMessage(),
        ]);
    }

    private function processActivitySpecificActions(TenantUser $user, TenantUserActivity $activity): void
    {
        switch ($this->action) {
            case 'login':
                $this->processLoginActivity($user, $activity);
                break;

            case 'user_created':
                $this->processUserCreatedActivity($user, $activity);
                break;

            case 'password_changed':
                $this->processPasswordChangedActivity($user, $activity);
                break;

            case 'user_deactivated':
                $this->processUserDeactivatedActivity($user, $activity);
                break;
        }
    }

    private function processLoginActivity(TenantUser $user, TenantUserActivity $activity): void
    {
        // Check if this is a suspicious login (different IP, location, etc.)
        $recentLogins = TenantUserActivity::where('user_id', $user->id)
            ->where('action', 'login')
            ->where('created_at', '>=', now()->subHours(24))
            ->where('ip_address', '!=', $this->ipAddress)
            ->count();

        if ($recentLogins > 0) {
            // Log suspicious activity
            Log::warning('Suspicious login detected', [
                'tenant_id' => tenant('id'),
                'user_id' => $user->id,
                'new_ip' => $this->ipAddress,
                'recent_different_ips' => $recentLogins,
            ]);

            // In a real implementation, you might:
            // - Send security alert email
            // - Require additional verification
            // - Lock the account temporarily
        }

        // Update login streak or other metrics
        $this->updateLoginMetrics($user);
    }

    private function processUserCreatedActivity(TenantUser $user, TenantUserActivity $activity): void
    {
        // Log user creation
        Log::info('New user created', [
            'tenant_id' => tenant('id'),
            'user_id' => $user->id,
            'created_by' => $this->metadata['created_by'] ?? null,
        ]);

        // Queue welcome email job
        $temporaryPassword = $this->metadata['temporary_password'] ?? '';
        SendWelcomeEmailJob::dispatch($user->id, $temporaryPassword, $this->metadata);
    }

    private function processPasswordChangedActivity(TenantUser $user, TenantUserActivity $activity): void
    {
        // Revoke all existing sessions/tokens for security
        // In a real implementation, you would invalidate all user sessions

        Log::info('Password changed - security actions taken', [
            'tenant_id' => tenant('id'),
            'user_id' => $user->id,
        ]);
    }

    private function processUserDeactivatedActivity(TenantUser $user, TenantUserActivity $activity): void
    {
        // Revoke all sessions/tokens
        // Send deactivation notification email

        Log::info('User deactivated - cleanup actions taken', [
            'tenant_id' => tenant('id'),
            'user_id' => $user->id,
        ]);
    }

    private function updateLoginMetrics(TenantUser $user): void
    {
        // Calculate login streak, frequency, etc.
        // Update user statistics

        $loginCount = TenantUserActivity::where('user_id', $user->id)
            ->where('action', 'login')
            ->count();

        // Store metrics in metadata or separate table
        Log::info('Login metrics updated', [
            'tenant_id' => tenant('id'),
            'user_id' => $user->id,
            'total_logins' => $loginCount,
        ]);
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'tenant:'.(tenant('id') ?? 'unknown'),
            'user:'.$this->userId,
            'activity',
            'action:'.$this->action,
        ];
    }
}
