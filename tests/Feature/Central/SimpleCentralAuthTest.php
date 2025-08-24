<?php

namespace Tests\Feature\Central;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SimpleCentralAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_central_authentication_flow(): void
    {
        // Test login
        $user = User::factory()->create([
            'email' => 'central@test.com',
            'password' => bcrypt('password123'),
        ]);

        $loginResponse = $this->postJson('http://localhost/api/v1/login', [
            'email' => 'central@test.com',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
                'message',
            ]);

        // Test user endpoint
        $userResponse = $this->actingAs($user, 'web')
            ->getJson('http://localhost/api/v1/user');

        $userResponse->assertStatus(200)
            ->assertJsonStructure([
                'user' => ['id', 'name', 'email'],
            ]);

        // Test logout
        $logoutResponse = $this->actingAs($user, 'web')
            ->postJson('http://localhost/api/v1/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson(['message' => 'Logged out successfully']);
    }

    public function test_central_login_validation(): void
    {
        $response = $this->postJson('http://localhost/api/v1/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
