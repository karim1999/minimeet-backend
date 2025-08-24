<?php

namespace Tests\Feature\Central;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Tests\TestCase;

class BasicAuthTest extends TestCase
{
    use DatabaseTransactions, WithoutMiddleware;

    public function test_central_user_can_login_simple(): void
    {
        // Create user directly
        $user = User::create([
            'name' => 'Test User',
            'email' => 'simple@test.com',
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('http://localhost/api/v1/login', [
            'email' => 'simple@test.com',
            'password' => 'password123',
        ]);

        if ($response->status() !== 200) {
            // Print response for debugging
            dump($response->json());
        }

        $response->assertStatus(200);
    }

    public function test_routes_are_working(): void
    {
        $response = $this->postJson('http://localhost/api/v1/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'wrong',
        ]);

        // Should get validation error, not 500 error
        $this->assertContains($response->status(), [422, 401]);
    }
}
