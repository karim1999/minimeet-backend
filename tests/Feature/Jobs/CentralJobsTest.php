<?php

declare(strict_types=1);

namespace Tests\Feature\Jobs;

use App\Jobs\Central\UpdateTenantStatsJob;
use App\Models\Central\TenantUserManagement;
use App\Models\Tenant;
use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use Tests\TestCase;

class CentralJobsTest extends TestCase
{
    public function test_update_tenant_stats_job_creates_management_record(): void
    {
        // Create a tenant
        $tenant = Tenant::factory()->create();

        // Initialize tenancy and create some test data
        tenancy()->initialize($tenant);

        TenantUser::factory()->count(5)->create(['is_active' => true, 'role' => 'member']);
        TenantUser::factory()->count(2)->create(['is_active' => false, 'role' => 'member']);
        TenantUser::factory()->create(['role' => 'admin', 'is_active' => true]);

        // Create some activities using an existing user
        $existingUser = TenantUser::first();
        TenantUserActivity::factory()->count(10)->create([
            'user_id' => $existingUser->id,
            'created_at' => now()->subHours(12),
        ]);

        tenancy()->end();

        // Execute the job
        $job = new UpdateTenantStatsJob($tenant->id);
        $job->handle();

        // Assert tenant management record was created
        $this->assertDatabaseHas('tenant_users_management', [
            'tenant_id' => $tenant->id,
            'user_count' => 8, // 5 active + 2 inactive + 1 admin
            'active_users' => 6, // 5 active + 1 admin
            'admin_users' => 1,
            'regular_users' => 7, // 5 active + 2 inactive (role set to 'member')
        ]);

        $management = TenantUserManagement::where('tenant_id', $tenant->id)->first();
        $this->assertNotNull($management);
        $this->assertEquals(10, $management->total_activities);
        $this->assertNotNull($management->last_activity_at);
    }

    public function test_update_tenant_stats_job_updates_existing_record(): void
    {
        // Create a tenant with existing management record
        $tenant = Tenant::factory()->create();
        $existingManagement = TenantUserManagement::factory()->create([
            'tenant_id' => $tenant->id,
            'user_count' => 10,
            'active_users' => 8,
        ]);

        // Initialize tenancy and create different test data
        tenancy()->initialize($tenant);

        TenantUser::factory()->count(3)->create(['is_active' => true]);
        TenantUser::factory()->count(1)->create(['is_active' => false]);

        tenancy()->end();

        // Execute the job
        $job = new UpdateTenantStatsJob($tenant->id);
        $job->handle();

        // Assert the existing record was updated
        $existingManagement->refresh();
        $this->assertEquals(4, $existingManagement->user_count);
        $this->assertEquals(3, $existingManagement->active_users);
    }

    public function test_update_tenant_stats_job_handles_empty_tenant(): void
    {
        // Create a tenant with no users
        $tenant = Tenant::factory()->create();

        // Execute the job
        $job = new UpdateTenantStatsJob($tenant->id);
        $job->handle();

        // Assert management record was created with zero counts
        $this->assertDatabaseHas('tenant_users_management', [
            'tenant_id' => $tenant->id,
            'user_count' => 0,
            'active_users' => 0,
            'admin_users' => 0,
            'manager_users' => 0,
            'regular_users' => 0,
            'total_activities' => 0,
        ]);
    }

    public function test_update_tenant_stats_job_calculates_recent_metrics(): void
    {
        $tenant = Tenant::factory()->create();

        tenancy()->initialize($tenant);

        // Create users with different creation dates
        TenantUser::factory()->count(2)->create([
            'created_at' => now()->subDays(45), // Old users
        ]);
        TenantUser::factory()->count(3)->create([
            'created_at' => now()->subDays(15), // Recent users (within 30 days)
        ]);

        // Create activities with different dates
        $recentUser = TenantUser::factory()->create();
        TenantUserActivity::create([
            'user_id' => $recentUser->id,
            'action' => 'login',
            'description' => 'Recent login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => now()->subDays(3),
        ]);

        TenantUserActivity::create([
            'user_id' => $recentUser->id,
            'action' => 'login',
            'description' => 'Today login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => now()->subHours(12),
        ]);

        tenancy()->end();

        // Execute the job
        $job = new UpdateTenantStatsJob($tenant->id);
        $job->handle();

        $management = TenantUserManagement::where('tenant_id', $tenant->id)->first();

        $this->assertEquals(4, $management->new_users_30d); // 3 from line 115 + 1 from line 120
        $this->assertEquals(2, $management->total_activities);
        $this->assertEquals(2, $management->recent_activities_24h); // Should be 2 if both are within 24h
        $this->assertEquals(1, $management->recently_active_users); // Users with login in last 7 days
    }

    public function test_update_tenant_stats_job_handles_role_distribution(): void
    {
        $tenant = Tenant::factory()->create();

        tenancy()->initialize($tenant);

        // Create users with different roles
        TenantUser::factory()->count(2)->create(['role' => 'admin']);
        TenantUser::factory()->count(3)->create(['role' => 'manager']);
        TenantUser::factory()->count(5)->create(['role' => 'member']);

        tenancy()->end();

        // Execute the job
        $job = new UpdateTenantStatsJob($tenant->id);
        $job->handle();

        $management = TenantUserManagement::where('tenant_id', $tenant->id)->first();

        $this->assertEquals(10, $management->user_count);
        $this->assertEquals(2, $management->admin_users);
        $this->assertEquals(3, $management->manager_users);
        $this->assertEquals(5, $management->regular_users);
    }

    public function test_update_tenant_stats_job_tracks_activity_breakdown(): void
    {
        $tenant = Tenant::factory()->create();

        tenancy()->initialize($tenant);

        $user = TenantUser::factory()->create();

        // Create different types of activities
        TenantUserActivity::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => 'Login activity',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => now()->subDays(15),
        ]);

        TenantUserActivity::create([
            'user_id' => $user->id,
            'action' => 'login',
            'description' => 'Another login',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => now()->subDays(10),
        ]);

        TenantUserActivity::create([
            'user_id' => $user->id,
            'action' => 'password_changed',
            'description' => 'Password change',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Test',
            'created_at' => now()->subDays(5),
        ]);

        tenancy()->end();

        // Execute the job
        $job = new UpdateTenantStatsJob($tenant->id);
        $job->handle();

        $management = TenantUserManagement::where('tenant_id', $tenant->id)->first();

        $this->assertIsArray($management->activity_breakdown);
        $this->assertEquals(2, $management->activity_breakdown['login'] ?? 0);
        $this->assertEquals(1, $management->activity_breakdown['password_changed'] ?? 0);
    }

    public function test_update_tenant_stats_job_identifies_most_active_users(): void
    {
        $tenant = Tenant::factory()->create();

        tenancy()->initialize($tenant);

        $user1 = TenantUser::factory()->create(['name' => 'Very Active User']);
        $user2 = TenantUser::factory()->create(['name' => 'Less Active User']);

        // Create more activities for user1
        TenantUserActivity::factory()->count(5)->create([
            'user_id' => $user1->id,
            'created_at' => now()->subDays(15),
        ]);

        TenantUserActivity::factory()->count(2)->create([
            'user_id' => $user2->id,
            'created_at' => now()->subDays(10),
        ]);

        tenancy()->end();

        // Execute the job
        $job = new UpdateTenantStatsJob($tenant->id);
        $job->handle();

        $management = TenantUserManagement::where('tenant_id', $tenant->id)->first();

        $this->assertIsArray($management->most_active_users);
        $this->assertNotEmpty($management->most_active_users);

        // The most active user should be first
        $mostActive = $management->most_active_users[0];
        $this->assertEquals($user1->id, $mostActive['user_id']);
        $this->assertEquals(5, $mostActive['activity_count']);
    }

    public function test_update_tenant_stats_job_handles_job_failure(): void
    {
        // Try to update stats for non-existent tenant
        $job = new UpdateTenantStatsJob('non-existent-tenant-id');

        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);

        $job->handle();
    }

    public function test_update_tenant_stats_job_has_correct_configuration(): void
    {
        $job = new UpdateTenantStatsJob('test-tenant-id');

        // Verify job configuration
        $this->assertEquals(3, $job->tries);
        $this->assertEquals(120, $job->timeout);

        // Verify tags
        $tags = $job->tags();
        $this->assertContains('central', $tags);
        $this->assertContains('tenant:test-tenant-id', $tags);
        $this->assertContains('stats-update', $tags);
    }

    public function test_update_tenant_stats_job_cleans_up_tenant_context(): void
    {
        $tenant = Tenant::factory()->create();

        // Ensure we start without tenant context
        $this->assertFalse(tenancy()->initialized);

        // Execute the job
        $job = new UpdateTenantStatsJob($tenant->id);
        $job->handle();

        // Ensure tenant context was cleaned up
        $this->assertFalse(tenancy()->initialized);

        // Verify the stats were still updated (job worked correctly)
        $this->assertDatabaseHas('tenant_users_management', [
            'tenant_id' => $tenant->id,
        ]);
    }

    public function test_update_tenant_stats_job_handles_tenant_initialization_failure(): void
    {
        // Create a tenant but then delete it to cause initialization failure
        $tenant = Tenant::factory()->create();
        $tenantId = $tenant->id;
        $tenant->delete();

        $job = new UpdateTenantStatsJob($tenantId);

        $this->expectException(\Exception::class);

        $job->handle();
    }

    public function test_multiple_tenant_stats_updates_are_isolated(): void
    {
        // Create two tenants
        $tenant1 = Tenant::factory()->create();
        $tenant2 = Tenant::factory()->create();

        // Add different data to each tenant
        tenancy()->initialize($tenant1);
        TenantUser::factory()->count(3)->create();
        tenancy()->end();

        tenancy()->initialize($tenant2);
        TenantUser::factory()->count(5)->create();
        tenancy()->end();

        // Update stats for both tenants
        $job1 = new UpdateTenantStatsJob($tenant1->id);
        $job1->handle();

        $job2 = new UpdateTenantStatsJob($tenant2->id);
        $job2->handle();

        // Verify each tenant has correct stats
        $management1 = TenantUserManagement::where('tenant_id', $tenant1->id)->first();
        $management2 = TenantUserManagement::where('tenant_id', $tenant2->id)->first();

        $this->assertEquals(3, $management1->user_count);
        $this->assertEquals(5, $management2->user_count);
    }
}
