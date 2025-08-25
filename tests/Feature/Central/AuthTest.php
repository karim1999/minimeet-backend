<?php

namespace Tests\Feature\Central;

use App\Models\Central\CentralUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_central_user_can_register_tenant(): void
    {
        $uniqueId = time().rand(1000, 9999);

        $response = $this->postJson('/api/v1/register', [
            'name' => 'John Doe',
            'email' => "john-{$uniqueId}@example.com",
            'password' => 'StrongPassword246!',
            'password_confirmation' => 'StrongPassword246!',
            'company_name' => 'Acme Corp',
            'domain' => "acme-test-{$uniqueId}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                    'tenant' => ['id', 'company_name', 'domain'],
                ],
                'meta' => ['timestamp', 'version'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => "john-{$uniqueId}@example.com",
        ]);
    }

    public function test_central_user_can_login(): void
    {
        $user = CentralUser::factory()->create([
            'email' => 'test-login-' . uniqid() . '@example.com',
            'password' => bcrypt('TestPassword123!'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $user->email,
            'password' => 'TestPassword123!',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    }

    public function test_central_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonStructure([
                'success',
                'message',
                'errors' => ['email'],
                'meta' => ['timestamp', 'error_code'],
            ]);
    }

    public function test_central_authenticated_user_can_access_protected_routes(): void
    {
        $user = CentralUser::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                ],
                'meta' => ['timestamp', 'version'],
            ]);
    }

    public function test_central_user_can_logout(): void
    {
        $user = CentralUser::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJson(['success' => true, 'message' => 'Logged out successfully']);
    }
}
