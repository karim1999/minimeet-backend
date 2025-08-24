<?php

namespace Tests\Feature\Central;

use App\Models\User;
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
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'company_name' => 'Acme Corp',
            'domain' => "acme-test-{$uniqueId}",
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'user' => ['id', 'name', 'email'],
                'tenant' => ['id', 'company_name', 'domain'],
            ]);

        $this->assertDatabaseHas('users', [
            'email' => "john-{$uniqueId}@example.com",
        ]);
    }

    public function test_central_user_can_login(): void
    {
        $user = User::factory()->create([
            'email' => 'test-login@example.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => 'test-login@example.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'message',
            ]);
    }

    public function test_central_login_with_invalid_credentials(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'wrongpassword',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    public function test_central_authenticated_user_can_access_protected_routes(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
            ]);
    }

    public function test_central_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user, 'web')
            ->postJson('/api/v1/logout');

        $response->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }
}
