<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Models\Tenant\TenantUser;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantUserManagementTest extends TestCase
{
    use WithFaker;

    protected $tenancy = true; // Enable automatic tenancy

    private TenantUser $adminUser;

    private TenantUser $regularUser;

    private string $adminToken;

    private string $userToken;

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the queue for testing
        Queue::fake();

        // Create test users
        $this->adminUser = TenantUser::factory()->create([
            'role' => 'admin',
            'is_active' => true,
        ]);

        $this->regularUser = TenantUser::factory()->create([
            'role' => 'member',
            'is_active' => true,
        ]);

        $this->adminToken = $this->adminUser->createToken('admin-token')->plainTextToken;
        $this->userToken = $this->regularUser->createToken('user-token')->plainTextToken;
    }

    public function test_admin_can_list_all_users(): void
    {
        TenantUser::factory()->count(3)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/users');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'users' => [
                        '*' => [
                            'id',
                            'name',
                            'email',
                            'role',
                            'is_active',
                            'created_at',
                            'updated_at',
                        ],
                    ],
                    'pagination' => [
                        'current_page',
                        'last_page',
                        'per_page',
                        'total',
                    ],
                ],
            ]);

        $data = $response->json('data');
        $this->assertGreaterThanOrEqual(5, $data['pagination']['total']); // 2 created + 3 factory
    }

    public function test_regular_user_can_list_users(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->getJson('/users');

        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_list_users(): void
    {
        $response = $this->getJson('/users');

        $response->assertStatus(401);
    }

    public function test_users_list_can_be_filtered(): void
    {
        TenantUser::factory()->create(['role' => 'admin']);
        TenantUser::factory()->create(['role' => 'manager']);
        TenantUser::factory()->create(['is_active' => false]);

        // Filter by role
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/users?role=admin');

        $response->assertStatus(200);
        $users = $response->json('data.users');
        foreach ($users as $user) {
            $this->assertEquals('admin', $user['role']);
        }

        // Filter by status
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/users?status=0');

        $response->assertStatus(200);
        $users = $response->json('data.users');
        foreach ($users as $user) {
            $this->assertFalse($user['is_active']);
        }
    }

    public function test_users_list_can_be_searched(): void
    {
        $searchUser = TenantUser::factory()->create([
            'name' => 'John Doe Searchable',
            'email' => 'searchable@example.com',
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/users?search=Searchable');

        $response->assertStatus(200);
        $users = $response->json('data.users');
        $this->assertNotEmpty($users);

        $found = false;
        foreach ($users as $user) {
            if ($user['id'] === $searchUser->id) {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found);
    }

    public function test_admin_can_create_user(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'SecureP@ssW0rd!',
            'role' => 'manager',
            'is_active' => true,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/users', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_active',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'name' => $userData['name'],
            'role' => 'manager',
            'is_active' => true,
        ]);
    }

    public function test_regular_user_cannot_create_user(): void
    {
        $userData = [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'SecurePass123!',
            'role' => 'member',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->postJson('/users', $userData);

        $response->assertStatus(403);
    }

    public function test_admin_can_view_specific_user(): void
    {
        $user = TenantUser::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/users/{$user->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                        'name' => $user->name,
                    ],
                ],
            ]);
    }

    public function test_admin_can_update_user(): void
    {
        $user = TenantUser::factory()->create(['role' => 'member']);

        $updateData = [
            'name' => 'Updated Name',
            'role' => 'manager',
            'is_active' => false,
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->putJson("/users/{$user->id}", $updateData);

        $response->assertStatus(200);

        $user->refresh();
        $this->assertEquals('Updated Name', $user->name);
        $this->assertEquals('manager', $user->role);
        $this->assertFalse($user->is_active);
    }

    public function test_regular_user_cannot_update_other_users(): void
    {
        $otherUser = TenantUser::factory()->create();

        $updateData = [
            'name' => 'Hacked Name',
            'role' => 'admin',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->putJson("/users/{$otherUser->id}", $updateData);

        $response->assertStatus(403);
    }

    public function test_user_can_view_own_profile(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->getJson('/users/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $this->regularUser->id,
                        'email' => $this->regularUser->email,
                    ],
                ],
            ]);
    }

    public function test_user_can_update_own_profile(): void
    {
        $updateData = [
            'name' => 'My New Name',
            'bio' => 'My updated bio',
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->putJson('/users/me', $updateData);

        $response->assertStatus(200);

        $this->regularUser->refresh();
        $this->assertEquals('My New Name', $this->regularUser->name);
        $this->assertEquals('My updated bio', $this->regularUser->bio);
    }

    public function test_user_cannot_change_own_role_via_profile_update(): void
    {
        $updateData = [
            'name' => 'My New Name',
            'role' => 'admin', // Should be ignored
        ];

        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->putJson('/users/me', $updateData);

        $response->assertStatus(200);

        $this->regularUser->refresh();
        $this->assertEquals('member', $this->regularUser->role); // Should remain unchanged
    }

    public function test_user_can_change_password(): void
    {
        $currentPassword = 'CurrentP@ssW0rd!';
        $newPassword = 'NewP@ssW0rd2024!';

        // Set known password
        $this->regularUser->update(['password' => Hash::make($currentPassword)]);

        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->postJson('/users/change-password', [
                'current_password' => $currentPassword,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

        $response->assertStatus(200);

        $this->regularUser->refresh();
        $this->assertTrue(Hash::check($newPassword, $this->regularUser->password));
    }

    public function test_user_cannot_change_password_with_wrong_current_password(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->userToken}")
            ->postJson('/users/change-password', [
                'current_password' => 'WrongPassword',
                'password' => 'NewP@ssW0rd!',
                'password_confirmation' => 'NewP@ssW0rd!',
            ]);

        $response->assertStatus(400);
    }

    public function test_admin_can_toggle_user_status(): void
    {
        $user = TenantUser::factory()->create(['is_active' => true]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/users/{$user->id}/toggle-status");

        $response->assertStatus(200);

        $user->refresh();
        $this->assertFalse($user->is_active);

        // Toggle again
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/users/{$user->id}/toggle-status");

        $response->assertStatus(200);

        $user->refresh();
        $this->assertTrue($user->is_active);
    }

    public function test_admin_cannot_toggle_own_status(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson("/users/{$this->adminUser->id}/toggle-status");

        $response->assertStatus(400);
    }

    public function test_admin_can_delete_user(): void
    {
        $user = TenantUser::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/users/{$user->id}");

        $response->assertStatus(200);

        $this->assertSoftDeleted('users', [
            'id' => $user->id,
        ]);
    }

    public function test_admin_cannot_delete_self(): void
    {
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->deleteJson("/users/{$this->adminUser->id}");

        $response->assertStatus(400);
    }

    public function test_admin_can_view_user_activity(): void
    {
        $user = TenantUser::factory()->create();

        // Create some activities
        $user->activities()->createMany([
            [
                'action' => 'login',
                'description' => 'User logged in',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent',
            ],
            [
                'action' => 'profile_updated',
                'description' => 'User updated profile',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent',
            ],
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson("/users/{$user->id}/activity");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'activities' => [
                        '*' => [
                            'id',
                            'action',
                            'description',
                            'ip_address',
                            'created_at',
                        ],
                    ],
                    'pagination',
                ],
            ]);

        $activities = $response->json('data.activities');
        $this->assertCount(2, $activities);
    }

    public function test_pagination_works_correctly(): void
    {
        TenantUser::factory()->count(25)->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->getJson('/users?per_page=10');

        $response->assertStatus(200);

        $pagination = $response->json('data.pagination');
        $this->assertEquals(10, $pagination['per_page']);
        $this->assertGreaterThan(1, $pagination['last_page']);
        $this->assertEquals(1, $pagination['current_page']);
    }

    public function test_user_creation_validation(): void
    {
        // Test required fields
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/users', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);

        // Test email uniqueness
        $existingUser = TenantUser::factory()->create();

        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/users', [
                'name' => 'Test User',
                'email' => $existingUser->email,
                'password' => 'SecurePass123!',
                'role' => 'member',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Test invalid role
        $response = $this->withHeader('Authorization', "Bearer {$this->adminToken}")
            ->postJson('/users', [
                'name' => 'Test User',
                'email' => $this->faker->safeEmail(),
                'password' => 'SecurePass123!',
                'role' => 'invalid_role',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['role']);
    }
}
