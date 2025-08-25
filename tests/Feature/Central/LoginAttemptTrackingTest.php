<?php

namespace Tests\Feature\Central;

use App\Models\Central\CentralUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LoginAttemptTrackingTest extends TestCase
{
    use DatabaseTransactions;

    public function test_successful_login_is_recorded(): void
    {
        $email = 'test-success-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $user = CentralUser::factory()->create([
            'email' => $email,
            'password' => bcrypt('TestPassword123!'),
        ]);

        $this->assertDatabaseCount('login_attempts', 0);

        $response = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'TestPassword123!',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('login_attempts', 1);
        $this->assertDatabaseHas('login_attempts', [
            'email' => $email,
            'success' => true,
            'user_id' => $user->id,
        ]);
    }

    public function test_failed_login_is_recorded(): void
    {
        $this->assertDatabaseCount('login_attempts', 0);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422);

        $this->assertDatabaseCount('login_attempts', 1);
        $this->assertDatabaseHas('login_attempts', [
            'email' => 'nonexistent@example.com',
            'success' => false,
            'user_id' => null,
        ]);
    }

    public function test_user_gets_locked_out_after_max_attempts(): void
    {
        // Set up config for testing
        config(['auth.login_attempts.max_attempts' => 3]);
        config(['auth.login_attempts.lockout_minutes' => 15]);

        $email = 'test@example.com';

        // Make 3 failed attempts
        for ($i = 0; $i < 3; $i++) {
            $response = $this->postJson('/api/v1/login', [
                'email' => $email,
                'password' => 'wrongpassword',
            ]);
            $response->assertStatus(422);
        }

        $this->assertDatabaseCount('login_attempts', 3);

        // Next attempt should be locked out
        $response = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429) // 429 Too Many Requests
            ->assertJsonStructure([
                'success',
                'message',
                'errors',
                'meta' => [
                    'timestamp',
                    'error_code',
                    'lockout_seconds_remaining',
                ],
            ])
            ->assertJson([
                'success' => false,
                'meta' => [
                    'error_code' => 'AUTH_LOCKED_OUT',
                ],
            ]);
    }

    public function test_successful_login_after_failed_attempts(): void
    {
        $email = 'test-after-failed-' . time() . '-' . rand(1000, 9999) . '@example.com';
        $user = CentralUser::factory()->create([
            'email' => $email,
            'password' => bcrypt('TestPassword123!'),
        ]);

        // Make 2 failed attempts
        for ($i = 0; $i < 2; $i++) {
            $this->postJson('/api/v1/login', [
                'email' => $email,
                'password' => 'wrongpassword',
            ])->assertStatus(422);
        }

        // Successful login should work and be recorded
        $response = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'TestPassword123!',
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseCount('login_attempts', 3);

        // Check that we have both failed and successful attempts
        $this->assertDatabaseHas('login_attempts', [
            'email' => $email,
            'success' => false,
        ]);

        $this->assertDatabaseHas('login_attempts', [
            'email' => $email,
            'success' => true,
            'user_id' => $user->id,
        ]);
    }

    public function test_ip_based_lockout(): void
    {
        config(['auth.login_attempts.max_attempts' => 3]);

        // Make failed attempts with different emails from the same IP
        $emails = ['test1@example.com', 'test2@example.com', 'test3@example.com'];

        foreach ($emails as $email) {
            $this->postJson('/api/v1/login', [
                'email' => $email,
                'password' => 'wrongpassword',
            ])->assertStatus(422);
        }

        // Next attempt from same IP should be locked out, even with different email
        $response = $this->postJson('/api/v1/login', [
            'email' => 'test4@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(429);
    }
}
