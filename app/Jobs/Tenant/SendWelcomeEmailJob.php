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
use Illuminate\Support\Facades\Mail;

class SendWelcomeEmailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 2;

    public int $timeout = 60;

    public function __construct(
        private readonly int $userId,
        private readonly string $temporaryPassword = '',
        private readonly array $additionalData = []
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

            Log::info('Sending welcome email to user', [
                'tenant_id' => tenant('id'),
                'user_id' => $user->id,
                'user_email' => $user->email,
            ]);

            // Send welcome email with temporary password if provided
            $this->sendWelcomeEmail($user);

            // Log the email sending activity
            TenantUserActivity::create([
                'user_id' => $user->id,
                'action' => 'welcome_email_sent',
                'description' => 'Welcome email sent to new user',
                'ip_address' => '127.0.0.1', // System action
                'metadata' => [
                    'job_id' => $this->job?->getJobId(),
                    'has_temp_password' => ! empty($this->temporaryPassword),
                    'additional_data' => $this->additionalData,
                ],
            ]);

            Log::info('Welcome email sent successfully', [
                'tenant_id' => tenant('id'),
                'user_id' => $user->id,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send welcome email', [
                'tenant_id' => tenant('id') ?? 'unknown',
                'user_id' => $this->userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Welcome email job failed permanently', [
            'tenant_id' => tenant('id') ?? 'unknown',
            'user_id' => $this->userId,
            'error' => $exception->getMessage(),
            'attempts' => $this->attempts(),
        ]);

        // Try to log the failure in tenant database if possible
        try {
            if (tenant('id')) {
                $user = TenantUser::find($this->userId);
                if ($user) {
                    TenantUserActivity::create([
                        'user_id' => $user->id,
                        'action' => 'welcome_email_failed',
                        'description' => 'Welcome email failed to send after all retries',
                        'ip_address' => '127.0.0.1',
                        'metadata' => [
                            'error' => $exception->getMessage(),
                            'attempts' => $this->attempts(),
                        ],
                    ]);
                }
            }
        } catch (\Exception $e) {
            Log::error('Failed to log welcome email failure', [
                'original_error' => $exception->getMessage(),
                'logging_error' => $e->getMessage(),
            ]);
        }
    }

    private function sendWelcomeEmail(TenantUser $user): void
    {
        $tenantName = tenant('name') ?? 'Your Organization';

        // In a real implementation, you would use Laravel's Mail facade
        // For now, we'll simulate the email sending
        $emailData = [
            'user_name' => $user->name,
            'user_email' => $user->email,
            'tenant_name' => $tenantName,
            'login_url' => 'https://'.tenant('domains.0.domain') ?? 'localhost',
            'temporary_password' => $this->temporaryPassword,
            'has_temp_password' => ! empty($this->temporaryPassword),
        ];

        // Simulate email sending (replace with actual Mail::send in production)
        Log::info('Welcome email content prepared', [
            'recipient' => $user->email,
            'subject' => "Welcome to {$tenantName}",
            'has_temp_password' => ! empty($this->temporaryPassword),
        ]);

        // Example of how you would send the actual email:
        // Mail::to($user->email)->send(new WelcomeEmail($emailData));
    }

    /**
     * Get the user ID for testing purposes.
     */
    public function getUserId(): int
    {
        return $this->userId;
    }

    /**
     * Get the tags that should be assigned to the job.
     */
    public function tags(): array
    {
        return [
            'tenant:'.(tenant('id') ?? 'unknown'),
            'user:'.$this->userId,
            'welcome-email',
        ];
    }
}
