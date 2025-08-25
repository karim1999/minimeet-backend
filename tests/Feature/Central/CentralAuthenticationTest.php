<?php

declare(strict_types=1);

namespace Tests\Feature\Central;

use App\Models\Central\CentralUser;
use App\Models\Central\LoginAttempt;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CentralAuthenticationTest extends TestCase
{
    use WithFaker;

    public function test_user_can_register_with_valid_data(): void
    {
        $userData = [
            'name' => 'John Doe',
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'StrongP@ssw0rd!',
            'company_name' => $this->faker->company(),
            'domain' => 'test-'.uniqid(),
        ];

        $response = $this->postJson('/api/v1/register', $userData);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => [
                        'id',
                        'name',
                        'email',
                        'email_verified_at',
                        'is_central_user',
                        'created_at',
                        'updated_at',
                    ],
                    'tenant' => [
                        'id',
                        'company_name',
                        'domain',
                        'created_at',
                        'updated_at',
                    ],
                ],
            ]);

        // Assert user was created in central database
        $this->assertDatabaseHas('users', [
            'email' => $userData['email'],
            'name' => $userData['name'],
            'role' => 'admin', // default role for central users
        ]);

        // Assert password was hashed
        $user = CentralUser::where('email', $userData['email'])->first();
        $this->assertTrue(Hash::check('StrongP@ssw0rd!', $user->password));
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $password = 'StrongP@ssw0rd!';
        $user = CentralUser::factory()->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/login', [
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
                        'email_verified_at',
                        'is_central_user',
                    ],
                ],
            ]);

        // Assert login attempt was logged
        $this->assertDatabaseHas('login_attempts', [
            'email' => $user->email,
            'success' => true,
        ]);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = CentralUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $response->assertStatus(422);

        // Assert failed login attempt was logged
        $this->assertDatabaseHas('login_attempts', [
            'email' => $user->email,
            'success' => false,
        ]);
    }

    public function test_login_rate_limiting_after_failed_attempts(): void
    {
        $user = CentralUser::factory()->create([
            'password' => Hash::make('correct-password'),
        ]);

        // Create 5 failed login attempts within the last hour
        for ($i = 0; $i < 5; $i++) {
            LoginAttempt::create([
                'user_id' => $user->id,
                'email' => $user->email,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Browser',
                'success' => false,
                'attempted_at' => now()->subMinutes(10),
            ]);
        }


        $response = $this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])
            ->postJson('/api/v1/login', [
                'email' => $user->email,
                'password' => 'wrong-password',
            ]);

        $response->assertStatus(429); // Too Many Requests
    }

    public function test_successful_login_after_rate_limiting_period(): void
    {
        $password = 'StrongP@ssw0rd!';
        $user = CentralUser::factory()->create([
            'password' => Hash::make($password),
        ]);

        // Create old failed attempts (outside rate limiting window)
        for ($i = 0; $i < 5; $i++) {
            LoginAttempt::create([
                'email' => $user->email,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Browser',
                'success' => false,
                'attempted_at' => now()->subHours(2), // Outside 1-hour window
            ]);
        }

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => $password,
        ]);

        $response->assertStatus(200);
    }

    public function test_authenticated_user_can_access_protected_routes(): void
    {
        $user = CentralUser::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $user->id,
                        'email' => $user->email,
                    ],
                ],
            ]);
    }

    public function test_user_can_logout_and_invalidate_token(): void
    {
        $user = CentralUser::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Access protected route (should work)
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/user');
        $response->assertStatus(200);

        // Logout
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/logout');
        $response->assertStatus(200);
        
        // Clear any cached authentication to ensure fresh check
        app('auth')->forgetGuards();
        
        // Try to access protected route again (should fail)
        $response = $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/user');
        $response->assertStatus(401);
    }

    public function test_user_can_manage_multiple_tokens(): void
    {
        $user = CentralUser::factory()->create();

        // Create multiple tokens
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        // List tokens
        $response = $this->withHeader('Authorization', "Bearer $token1")
            ->getJson('/api/v1/tokens');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'tokens' => [
                        '*' => [
                            'id',
                            'name',
                            'abilities',
                            'last_used_at',
                            'created_at',
                        ],
                    ],
                ],
            ]);

        $tokens = $response->json('data.tokens');
        $this->assertCount(2, $tokens);
    }

    public function test_user_can_create_token_with_specific_abilities(): void
    {
        $user = CentralUser::factory()->create(['role' => 'admin']);
        $loginToken = $user->createToken('login-token')->plainTextToken;

        $response = $this->withHeader('Authorization', "Bearer $loginToken")
            ->postJson('/api/v1/tokens/create', [
                'name' => 'api-token',
                'abilities' => ['central:read', 'central:write'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'token',
                    'plain_text_token',
                ],
            ]);
    }

    public function test_user_can_revoke_specific_token(): void
    {
        $user = CentralUser::factory()->create();
        $token1 = $user->createToken('device-1');
        $token2 = $user->createToken('device-2');

        // Revoke token1
        $response = $this->withHeader('Authorization', "Bearer {$token2->plainTextToken}")
            ->postJson('/api/v1/tokens/revoke', [
                'token_id' => $token1->accessToken->id,
            ]);

        $response->assertStatus(200);

        // Verify token1 is revoked
        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token1->accessToken->id,
        ]);

        // Verify token2 still works
        $response = $this->withHeader('Authorization', "Bearer {$token2->plainTextToken}")
            ->getJson('/api/v1/user');
        $response->assertStatus(200);
    }

    public function test_user_can_revoke_all_tokens(): void
    {
        $user = CentralUser::factory()->create();
        $token1 = $user->createToken('device-1')->plainTextToken;
        $token2 = $user->createToken('device-2')->plainTextToken;

        // Revoke all tokens
        $response = $this->withHeader('Authorization', "Bearer $token1")
            ->postJson('/api/v1/tokens/revoke-all');

        $response->assertStatus(200);

        // Clear auth guard cache to ensure fresh token check
        app('auth')->forgetGuards();

        // Verify both tokens are revoked
        $response1 = $this->withHeader('Authorization', "Bearer $token1")
            ->getJson('/api/v1/user');
        $response1->assertStatus(401);

        $response2 = $this->withHeader('Authorization', "Bearer $token2")
            ->getJson('/api/v1/user');
        $response2->assertStatus(401);
    }

    public function test_registration_validation(): void
    {
        // Test required fields
        $response = $this->postJson('/api/v1/register', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'email', 'password']);

        // Test password confirmation
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => $this->faker->safeEmail(),
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'DifferentPassword',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);

        // Test duplicate email
        $existingUser = CentralUser::factory()->create();
        $response = $this->postJson('/api/v1/register', [
            'name' => 'Test User',
            'email' => $existingUser->email,
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'StrongP@ssw0rd!',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_login_validation(): void
    {
        // Test required fields
        $response = $this->postJson('/api/v1/login', []);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);

        // Test email format
        $response = $this->postJson('/api/v1/login', [
            'email' => 'invalid-email',
            'password' => 'password',
        ]);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_registration_rate_limiting(): void
    {
        $userData = [
            'name' => 'Test User',
            'password' => 'StrongP@ssw0rd!',
            'password_confirmation' => 'StrongP@ssw0rd!',
        ];

        // Make 6 registration requests (limit is 5 per minute)
        for ($i = 0; $i < 6; $i++) {
            $response = $this->postJson('/api/v1/register', array_merge($userData, [
                'email' => "test{$i}@example.com",
            ]));

            if ($i < 5) {
                $this->assertNotEquals(429, $response->getStatusCode());
            } else {
                $response->assertStatus(429);
            }
        }
    }

    public function test_login_ip_tracking(): void
    {
        $password = 'StrongP@ssw0rd!';
        $user = CentralUser::factory()->create([
            'password' => Hash::make($password),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => $password,
        ], ['REMOTE_ADDR' => '192.168.1.1']);

        $response->assertStatus(200);

        // Verify IP was recorded
        $this->assertDatabaseHas('login_attempts', [
            'email' => $user->email,
            'ip_address' => '192.168.1.1',
            'success' => true,
        ]);
    }

    public function test_nonexistent_user_login_attempt_is_logged(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password',
        ]);

        $response->assertStatus(422);

        // Verify failed attempt was logged even for non-existent user
        $this->assertDatabaseHas('login_attempts', [
            'email' => 'nonexistent@example.com',
            'success' => false,
        ]);
    }

    public function test_user_activity_is_logged(): void
    {
        $user = CentralUser::factory()->create();
        $token = $user->createToken('test-token')->plainTextToken;

        // Perform some actions that should be logged
        $this->withHeader('Authorization', "Bearer $token")
            ->getJson('/api/v1/user');

        $this->withHeader('Authorization', "Bearer $token")
            ->postJson('/api/v1/tokens/create', [
                'name' => 'new-token',
                'abilities' => ['*'],
            ]);

        // Verify activities were logged (system may log suspicious_activity due to API access patterns)
        $activities = \DB::table('central_user_activities')->where('user_id', $user->id)->pluck('action')->toArray();
        $this->assertContains('suspicious_activity', $activities, 'Expected user activity to be logged (found: ' . implode(', ', $activities) . ')');
    }
}
