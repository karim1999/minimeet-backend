<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Tenant\ProcessUserActivityJob;
use App\Jobs\Tenant\SendWelcomeEmailJob;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantJobsTest extends TestCase
{
    use WithFaker;

    protected $tenancy = true; // Enable automatic tenancy

    protected function setUp(): void
    {
        parent::setUp();
    }

    public function test_process_user_activity_job_creates_activity_record(): void
    {
        $user = TenantUser::factory()->create();

        $job = new ProcessUserActivityJob(
            $user->id,
            'test_action',
            'Test activity description',
            '127.0.0.1',
            'Test User Agent',
            ['test_key' => 'test_value']
        );

        // Execute the job
        $job->handle();

        // Assert activity was created
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'action' => 'test_action',
            'description' => 'Test activity description',
            'ip_address' => '127.0.0.1',
        ]);

        $activity = TenantUserActivity::where('user_id', $user->id)->first();
        $this->assertEquals('test_value', $activity->metadata['test_key']);
    }

    public function test_process_user_activity_job_handles_login_action(): void
    {
        $user = TenantUser::factory()->create();

        $job = new ProcessUserActivityJob(
            $user->id,
            'login',
            'User logged in',
            '127.0.0.1',
            'Test Browser'
        );

        // Execute the job
        $job->handle();

        // Assert activity was created
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'action' => 'login',
        ]);

        // Assert last_login_at was updated
        $user->refresh();
        $this->assertNotNull($user->last_login_at);
        $this->assertTrue($user->last_login_at->isToday());
    }

    public function test_process_user_activity_job_handles_user_created_action(): void
    {
        Queue::fake();

        $user = TenantUser::factory()->create();

        $job = new ProcessUserActivityJob(
            $user->id,
            'user_created',
            'User was created',
            '127.0.0.1',
            'Admin Panel',
            ['temporary_password' => 'temp123']
        );

        // Execute the job
        $job->handle();

        // Assert welcome email job was dispatched
        Queue::assertPushed(SendWelcomeEmailJob::class, function ($job) use ($user) {
            return $job->getUserId() === $user->id;
        });
    }

    public function test_send_welcome_email_job_logs_activity(): void
    {
        $user = TenantUser::factory()->create();

        $job = new SendWelcomeEmailJob(
            $user->id,
            'temp123',
            ['created_by' => 'admin']
        );

        // Execute the job
        $job->handle();

        // Assert welcome email activity was logged
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'action' => 'welcome_email_sent',
            'description' => 'Welcome email sent to new user',
        ]);

        $activity = TenantUserActivity::where('user_id', $user->id)
            ->where('action', 'welcome_email_sent')
            ->first();

        $this->assertTrue($activity->metadata['has_temp_password']);
        $this->assertEquals('admin', $activity->metadata['additional_data']['created_by']);
    }

    public function test_send_welcome_email_job_handles_failure_gracefully(): void
    {
        // Create job with invalid user ID
        $job = new SendWelcomeEmailJob(999999);

        try {
            $job->handle();
            $this->fail('Job should have thrown an exception for invalid user ID');
        } catch (\Exception $e) {
            // Assert the exception was handled properly
            $this->assertInstanceOf(\Illuminate\Database\Eloquent\ModelNotFoundException::class, $e);
        }
    }

    public function test_jobs_preserve_tenant_context(): void
    {
        $user = TenantUser::factory()->create();
        $currentTenantId = tenant('id');

        $job = new ProcessUserActivityJob(
            $user->id,
            'test_action',
            'Test description',
            '127.0.0.1',
            'Test Agent'
        );

        // Ensure we're in tenant context
        $this->assertTrue(tenancy()->initialized);
        $this->assertEquals($currentTenantId, tenant('id'));

        // Execute the job
        $job->handle();

        // Verify we're still in the same tenant context
        $this->assertTrue(tenancy()->initialized);
        $this->assertEquals($currentTenantId, tenant('id'));

        // Verify the activity was created in the correct tenant database
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'action' => 'test_action',
        ]);
    }

    public function test_job_retries_on_failure(): void
    {
        $user = TenantUser::factory()->create();

        // Mock a job that will fail
        $job = new class($user->id, 'test_action', 'Test description', '127.0.0.1', 'Test Agent') extends ProcessUserActivityJob
        {
            private int $attempts = 0;

            public function handle(): void
            {
                $this->attempts++;

                // Fail on first attempt, succeed on second
                if ($this->attempts === 1) {
                    throw new \Exception('Simulated job failure');
                }

                parent::handle();
            }
        };

        // First execution should fail
        try {
            $job->handle();
            $this->fail('Job should have failed on first attempt');
        } catch (\Exception $e) {
            $this->assertEquals('Simulated job failure', $e->getMessage());
        }

        // Second execution should succeed
        $job->handle();

        // Verify activity was eventually created
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
        ]);
    }

    public function test_job_tags_are_properly_set(): void
    {
        $user = TenantUser::factory()->create();
        $tenantId = tenant('id');

        $activityJob = new ProcessUserActivityJob(
            $user->id,
            'test_action',
            'Test description',
            '127.0.0.1',
            'Test Agent'
        );

        $welcomeJob = new SendWelcomeEmailJob($user->id);

        // Test activity job tags
        $activityTags = $activityJob->tags();
        $this->assertContains("tenant:{$tenantId}", $activityTags);
        $this->assertContains("user:{$user->id}", $activityTags);
        $this->assertContains('activity', $activityTags);
        $this->assertContains('action:test_action', $activityTags);

        // Test welcome email job tags
        $welcomeTags = $welcomeJob->tags();
        $this->assertContains("tenant:{$tenantId}", $welcomeTags);
        $this->assertContains("user:{$user->id}", $welcomeTags);
        $this->assertContains('welcome-email', $welcomeTags);
    }

    public function test_password_changed_activity_processing(): void
    {
        $user = TenantUser::factory()->create();

        $job = new ProcessUserActivityJob(
            $user->id,
            'password_changed',
            'User changed their password',
            '127.0.0.1',
            'User Browser'
        );

        // Execute the job
        $job->handle();

        // Assert activity was created
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'action' => 'password_changed',
        ]);

        // In a real implementation, this would revoke sessions/tokens
        // For now, just verify the activity was logged
    }

    public function test_user_deactivated_activity_processing(): void
    {
        $user = TenantUser::factory()->create();

        $job = new ProcessUserActivityJob(
            $user->id,
            'user_deactivated',
            'User was deactivated',
            '127.0.0.1',
            'Admin Panel'
        );

        // Execute the job
        $job->handle();

        // Assert activity was created
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'action' => 'user_deactivated',
        ]);
    }

    public function test_job_timeout_configuration(): void
    {
        $job = new ProcessUserActivityJob(1, 'test', 'test', '127.0.0.1', 'test');

        // Verify timeout settings
        $this->assertEquals(30, $job->timeout);
        $this->assertEquals(2, $job->tries);
    }

    public function test_welcome_email_job_timeout_configuration(): void
    {
        $job = new SendWelcomeEmailJob(1);

        // Verify timeout settings
        $this->assertEquals(60, $job->timeout);
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(2, $job->maxExceptions);
    }

    public function test_suspicious_login_detection(): void
    {
        $user = TenantUser::factory()->create();

        // Create a login from one IP
        TenantUserActivity::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => 'User logged in',
            'ip_address' => '192.168.1.1',
            'created_at' => now()->subHours(1),
        ]);

        // Create another login from different IP (should be flagged as suspicious)
        $job = new ProcessUserActivityJob(
            $user->id,
            'login',
            'User logged in from different location',
            '10.0.0.1',
            'Browser 2'
        );

        $job->handle();

        // Verify the login was processed
        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'action' => 'login',
            'ip_address' => '10.0.0.1',
        ]);

        // In a real implementation, this would trigger security alerts
        // For now, just verify the activity was logged
    }
}
