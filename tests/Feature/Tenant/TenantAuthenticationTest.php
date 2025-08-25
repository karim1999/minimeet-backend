<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant;

use App\Jobs\Tenant\ProcessUserActivityJob;
use App\Models\Tenant\TenantUser;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class TenantAuthenticationTest extends TestCase
{
    use WithFaker;

    protected $tenancy = true; // Enable automatic tenancy

    protected function setUp(): void
    {
        parent::setUp();

        // Fake the queue for testing
        Queue::fake();
    }

    public function test_user_can_register_with_valid_data(): void
    {
        $userData = [
            'name' => 'Jane Smith',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'StrongP@ssw0rd!',
            'role' => 'member',
        ];

        $response = $this->postJson('/auth/register', $userData);

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
                        'created_at',
                        'updated_at',
                    ],
                    'token',
                ],
            ]);

        // Assert user was created in database
        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'name' => $userData['name'],
            'role' => 'member',
            'is_active' => true,
        ]);

        // Assert password was hashed
        $user = TenantUser::where('email', $userData['email'])->first();
        $this->assertTrue(Hash::check('StrongP@ssw0rd!', $user->password));

        // Assert job was dispatched
        Queue::assertPushed(ProcessUserActivityJob::class);
    }

    public function test_user_cannot_register_with_weak_password(): void
    {
        $userData = [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => '123',
            'password_confirmation' => '123',
        ];

        $response = $this->postJson('/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        // Create existing user
        $existingUser = TenantUser::factory()->create();

        $userData = [
            'name' => $this->faker->name(),
            'email' => $existingUser->email,
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'StrongP@ssw0rd!',
        ];

        $response = $this->postJson('/auth/register', $userData);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $password = 'StrongP@ssw0rd!';
        $user = TenantUser::factory()->create([
            'password' => Hash::make($password),
            'is_active' => true,
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200)
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
                    'token',
                ],
            ]);

        // Assert job was dispatched
        Queue::assertPushed(ProcessUserActivityJob::class);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = TenantUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(400);
    }

    public function test_inactive_user_cannot_login(): void
    {
        $password = 'StrongP@ssw0rd!';
        $user = TenantUser::factory()->create([
            'password' => Hash::make($password),
            'is_active' => false,
        ]);

        $response = $this->postJson('/auth/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(400);
    }

    public function test_authenticated_user_can_get_profile(): void
    {
        $user = TenantUser::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/auth/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
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

        $response->assertJson([
            'data' => [
                'user' => [
                    'id' => $user->id,
                    'email' => $user->email,
                ],
            ],
        ]);
    }

    public function test_unauthenticated_user_cannot_get_profile(): void
    {
        $response = $this->getJson('/auth/user');

        $response->assertStatus(401);
    }

    public function test_user_can_logout(): void
    {
        $user = TenantUser::factory()->create();
        $tokenData = $user->createToken('test-token');
        $token = $tokenData->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/auth/logout');

        $response->assertStatus(200);

        // Assert job was dispatched
        Queue::assertPushed(ProcessUserActivityJob::class);

        // Assert token was deleted from database
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $tokenData->accessToken->id,
        ]);

        // Clear auth guard cache to ensure fresh token check
        app('auth')->forgetGuards();
        
        // Try to access protected route with same token (should fail)
        $profileResponse = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/auth/user');

        $profileResponse->assertStatus(401);
    }

    public function test_forgot_password_with_valid_email(): void
    {
        $user = TenantUser::factory()->create(['is_active' => true]);

        $response = $this->postJson('/auth/forgot-password', [
            'email' => $user->email,
        ]);

        $response->assertStatus(200);
    }

    public function test_forgot_password_with_invalid_email(): void
    {
        $response = $this->postJson('/auth/forgot-password', [
            'email' => 'nonexistent@example.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_validation_rules(): void
    {
        // Test required fields
        $response = $this->postJson('/auth/register', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        // Test email format
        $response = $this->postJson('/auth/register', [
            'name' => 'Test User',
            'email' => 'invalid-email',
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'StrongP@ssw0rd!',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);

        // Test password confirmation
        $response = $this->postJson('/auth/register', [
            'name' => 'Test User',
            'email' => $this->faker->safeEmail(),
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'DifferentPassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    public function test_login_validation_rules(): void
    {
        // Test required fields
        $response = $this->postJson('/auth/login', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        // Test email format
        $response = $this->postJson('/auth/login', [
            'email' => 'invalid-email',
            'password' => 'password',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_rate_limiting_on_auth_routes(): void
    {
        $userData = [
            'name' => 'Test User',
            'email' => $this->faker->safeEmail(),
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'StrongP@ssw0rd!',
        ];

        // Make multiple requests to test rate limiting (5 requests per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/auth/register', array_merge($userData, [
                'email' => $this->faker->unique()->safeEmail(),
            ]));

            if ($i < 5) {
                // First 5 should succeed or fail for other reasons
                $this->assertNotEquals(429, $response->getStatusCode());
            } else {
                // 6th should be rate limited
                $response->assertStatus(429);
            }
        }
    }
}
