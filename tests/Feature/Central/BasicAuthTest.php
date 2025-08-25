<?php

namespace Tests\Feature\Central;

use App\Models\Central\CentralUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BasicAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_central_user_can_login_simple(): void
    {
        // Create user directly
        $email = 'simple-' . uniqid() . '@test.com';
        $user = CentralUser::create([
            'name' => 'Test User',
            'email' => $email,
            'password' => bcrypt('password123'),
        ]);

        $response = $this->postJson('/api/v1/login', [
            'email' => $email,
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
        $response = $this->postJson('/api/v1/login', [
            'email' => 'nonexistent@test.com',
            'password' => 'wrong',
        ]);

        // Should get validation error, not 500 error
        $this->assertContains($response->status(), [422, 401]);
    }
}
