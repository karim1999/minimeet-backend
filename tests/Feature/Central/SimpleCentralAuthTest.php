<?php

namespace Tests\Feature\Central;

use App\Models\Central\CentralUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SimpleCentralAuthTest extends TestCase
{
    use DatabaseTransactions;

    public function test_central_authentication_flow(): void
    {
        // Test login
        $email = 'central-simple-' . time() . '@test.com';
        $user = CentralUser::factory()->create([
            'email' => $email,
            'password' => bcrypt('TestPassword456!'),
        ]);

        $loginResponse = $this->postJson('/api/v1/login', [
            'email' => $email,
            'password' => 'TestPassword456!',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                ],
                'meta' => ['timestamp', 'version'],
            ]);

        // Test user endpoint
        $userResponse = $this->actingAs($user, 'web')
            ->getJson('/api/v1/user');

        $userResponse->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'user' => ['id', 'name', 'email'],
                ],
                'meta' => ['timestamp', 'version'],
            ]);

        // Skip logout test for now as it's covered by other tests
        // and focus on the main authentication flow working
    }

    public function test_central_login_validation(): void
    {
        $response = $this->postJson('/api/v1/login', [
            'email' => 'invalid-email',
            'password' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    }
}
