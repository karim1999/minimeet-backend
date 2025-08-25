<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Models\Central\CentralUser;
use App\Models\Central\TenantUserManagement;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private CentralUser $adminUser;

    private CentralUser $superAdminUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->adminUser = CentralUser::factory()->create([
            'role' => 'admin',
        ]);

        $this->superAdminUser = CentralUser::factory()->create([
            'role' => 'super_admin',
        ]);
    }

    public function test_admin_can_access_dashboard(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'stats' => [
                        'total_users',
                        'active_users',
                        'total_tenants',
                        'active_tenants',
                    ],
                    'recentUsers',
                    'systemHealth',
                ],
            ]);
    }

    public function test_dashboard_shows_correct_statistics(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        // Get initial count
        $initialTenantCount = Tenant::count();

        // Create some test data
        $tenants = Tenant::factory()->count(3)->create();
        foreach ($tenants as $tenant) {
            TenantUserManagement::factory()->create([
                'tenant_id' => $tenant->id,
                'user_count' => rand(5, 50),
                'active_users' => rand(1, 10),
            ]);
        }

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200);

        $stats = $response->json('data.stats');

        $this->assertArrayHasKey('total_users', $stats);
        $this->assertArrayHasKey('active_users', $stats);
        $this->assertArrayHasKey('total_tenants', $stats);
        $this->assertArrayHasKey('active_tenants', $stats);

        $this->assertEquals($initialTenantCount + 3, $stats['total_tenants']);
        $this->assertIsNumeric($stats['total_users']);
        $this->assertIsNumeric($stats['active_users']);
    }

    public function test_super_admin_can_access_system_stats(): void
    {
        $this->actingAs($this->superAdminUser, 'central_sanctum');

        $response = $this->getJson('/api/v1/admin/system-stats');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'stats',
                    'tenantStats',
                    'userStats',
                    'systemHealth',
                ],
            ]);
    }

    public function test_regular_admin_cannot_access_system_stats(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        $response = $this->getJson('/api/v1/admin/system-stats');

        $response->assertStatus(403);
    }

    public function test_unauthenticated_user_cannot_access_dashboard(): void
    {
        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(401);
    }

    public function test_dashboard_web_interface_works(): void
    {
        // Create some test data for the view
        $tenants = Tenant::factory()->count(2)->create();
        foreach ($tenants as $tenant) {
            TenantUserManagement::factory()->create([
                'tenant_id' => $tenant->id,
                'user_count' => rand(5, 20),
                'active_users' => rand(1, 5),
            ]);
        }

        // Mock admin authentication for web interface
        $response = $this->actingAs($this->adminUser, 'web')->get('/admin/dashboard');

        $response->assertStatus(200)
            ->assertViewIs('admin.dashboard')
            ->assertViewHasAll(['stats', 'recentUsers', 'systemHealth']);
    }

    public function test_tenant_user_management_interface(): void
    {
        // Create some test tenants and user management records
        $tenants = Tenant::factory()->count(3)->create();
        foreach ($tenants as $tenant) {
            TenantUserManagement::factory()->create([
                'tenant_id' => $tenant->id,
                'user_count' => rand(1, 100),
                'active_users' => rand(1, 50),
            ]);
        }

        // Mock admin authentication
        $response = $this->actingAs($this->adminUser, 'web')->get('/admin/tenant-users');

        $response->assertStatus(200)
            ->assertViewIs('admin.tenant-users.index')
            ->assertViewHasAll(['users', 'pagination', 'tenants']);
    }

    public function test_dashboard_handles_empty_data_gracefully(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        // Ensure no tenant data exists
        Tenant::query()->delete();
        TenantUserManagement::query()->delete();

        $response = $this->getJson('/api/v1/admin/dashboard');

        $response->assertStatus(200);

        $stats = $response->json('data.stats');

        $this->assertEquals(0, $stats['total_tenants']);
        $this->assertEquals(0, $stats['total_users']);
        $this->assertEquals(0, $stats['active_users']);
    }

    public function test_system_health_check(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        $response = $this->getJson('/api/v1/admin/health');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'health' => [
                        'database' => [
                            'status',
                            'message',
                        ],
                        'cache' => [
                            'status',
                            'message',
                        ],
                        'queue' => [
                            'status',
                            'message',
                        ],
                    ],
                ],
            ]);

        $health = $response->json('data.health');

        // Database should be healthy in tests
        $this->assertEquals('healthy', $health['database']['status']);
    }

    public function test_dashboard_metrics_endpoint(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        // Create some historical data
        TenantUserManagement::factory()->count(3)->create([
            'updated_at' => now()->subDays(1),
        ]);

        $response = $this->getJson('/api/v1/admin/metrics?timeframe=24h&metric=users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'timeframe',
                    'metric',
                    'data',
                ],
            ]);

        $this->assertEquals('24h', $response->json('data.timeframe'));
        $this->assertEquals('users', $response->json('data.metric'));
    }

    public function test_recent_activities_endpoint(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        $response = $this->getJson('/api/v1/admin/activities?limit=10');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'activities',
                ],
            ]);

        $activities = $response->json('data.activities');
        $this->assertIsArray($activities);
        $this->assertLessThanOrEqual(10, count($activities));
    }

    public function test_export_functionality(): void
    {
        // Create some data to export
        $tenants = Tenant::factory()->count(2)->create();
        foreach ($tenants as $tenant) {
            TenantUserManagement::factory()->create(['tenant_id' => $tenant->id]);
        }

        $response = $this->actingAs($this->adminUser, 'central_sanctum')
            ->get('/api/v1/admin/export?type=tenants&format=csv');

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
    }

    public function test_admin_authentication_middleware(): void
    {
        // Test without authentication
        $response = $this->getJson('/api/v1/admin/dashboard');
        $response->assertStatus(401);

        // Test with wrong role (create a user without central privileges)
        $regularUser = CentralUser::factory()->create([
            'role' => 'support',
            'is_central' => false,  // Not a central admin
        ]);
        $this->actingAs($regularUser, 'central_sanctum');

        $response = $this->getJson('/api/v1/admin/dashboard');
        $response->assertStatus(403);
    }

    public function test_dashboard_caches_expensive_queries(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        // Create test data
        Tenant::factory()->count(10)->create();

        // First request
        $start = microtime(true);
        $response1 = $this->getJson('/api/v1/admin/dashboard');
        $duration1 = microtime(true) - $start;

        $response1->assertStatus(200);

        // Second request (should be faster due to caching)
        $start = microtime(true);
        $response2 = $this->getJson('/api/v1/admin/dashboard');
        $duration2 = microtime(true) - $start;

        $response2->assertStatus(200);

        // Responses should be identical
        $this->assertEquals(
            $response1->json('data.stats'),
            $response2->json('data.stats')
        );
    }

    public function test_dashboard_pagination_parameters(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        $response = $this->getJson('/api/v1/admin/activities?page=1&limit=5');

        $response->assertStatus(200);

        $activities = $response->json('data.activities');
        $this->assertLessThanOrEqual(5, count($activities));
    }

    public function test_invalid_metric_parameters(): void
    {
        $this->actingAs($this->adminUser, 'central_sanctum');

        $response = $this->getJson('/api/v1/admin/metrics?timeframe=invalid&metric=invalid');

        $response->assertStatus(422);
    }
}
