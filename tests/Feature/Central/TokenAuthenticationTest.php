<?php

namespace Tests\Feature\Central;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class TokenAuthenticationTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    public function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'email' => 'token-test@example.com',
            'password' => bcrypt('TestPassword123!'),
        ]);
    }

    public function test_authenticated_user_can_create_token(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/tokens/create', [
                'name' => 'Test API Token',
                'abilities' => ['central:*'],
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token' => [
                        'id',
                        'name',
                        'abilities',
                        'expires_at',
                        'created_at',
                    ],
                    'plain_text_token',
                ],
                'meta' => ['timestamp', 'version'],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'token' => [
                        'name' => 'Test API Token',
                        'abilities' => ['central:*'],
                    ],
                ],
            ]);

        $this->assertDatabaseHas('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
            'name' => 'Test API Token',
        ]);
    }

    public function test_authenticated_user_can_list_tokens(): void
    {
        // Create a couple of tokens
        $token1 = $this->user->createToken('Token 1', ['central:read']);
        $token2 = $this->user->createToken('Token 2', ['central:*']);

        $response = $this->actingAs($this->user, 'web')
            ->getJson('/api/v1/tokens');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'abilities',
                        'last_used_at',
                        'expires_at',
                        'created_at',
                    ],
                ],
                'meta' => ['timestamp', 'version', 'total_count'],
            ])
            ->assertJson([
                'success' => true,
                'meta' => [
                    'total_count' => 2,
                ],
            ]);
    }

    public function test_user_can_access_api_with_token(): void
    {
        $token = $this->user->createToken('API Test Token', ['central:*']);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->getJson('/api/v1/user');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'user' => [
                        'id' => $this->user->id,
                        'email' => $this->user->email,
                    ],
                ],
            ]);
    }

    public function test_user_can_revoke_specific_token(): void
    {
        $token = $this->user->createToken('Token to revoke');

        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/tokens/revoke', [
                'token_id' => $token->accessToken->id,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Token revoked successfully',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_user_can_revoke_all_tokens(): void
    {
        // Create multiple tokens
        $token1 = $this->user->createToken('Token 1');
        $token2 = $this->user->createToken('Token 2');
        $token3 = $this->user->createToken('Token 3');

        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/tokens/revoke-all');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'All tokens revoked successfully',
                'data' => [
                    'revoked_count' => 3,
                ],
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'tokenable_id' => $this->user->id,
        ]);
    }

    public function test_user_can_revoke_current_token(): void
    {
        $token = $this->user->createToken('Current Token');

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->postJson('/api/v1/tokens/revoke-current');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Current token revoked successfully',
            ]);

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->id,
        ]);
    }

    public function test_invalid_token_returns_unauthorized(): void
    {
        $response = $this->withHeaders([
            'Authorization' => 'Bearer invalid_token_here',
        ])->getJson('/api/v1/user');

        $response->assertStatus(401);
    }

    public function test_token_creation_requires_authentication(): void
    {
        $response = $this->postJson('/api/v1/tokens/create', [
            'name' => 'Test Token',
        ]);

        $response->assertStatus(401);
    }

    public function test_token_creation_validates_input(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/tokens/create', [
                // Missing required name field
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    }

    public function test_cannot_revoke_nonexistent_token(): void
    {
        $response = $this->actingAs($this->user, 'web')
            ->postJson('/api/v1/tokens/revoke', [
                'token_id' => 99999, // Non-existent token ID
            ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Failed to revoke token',
            ]);
    }

    public function test_dual_authentication_works_with_both_session_and_token(): void
    {
        // Test with session authentication
        $response1 = $this->actingAs($this->user, 'web')
            ->getJson('/api/v1/user');
        $response1->assertStatus(200);

        // Test with token authentication
        $token = $this->user->createToken('Dual Auth Test');
        $response2 = $this->withHeaders([
            'Authorization' => 'Bearer '.$token->plainTextToken,
        ])->getJson('/api/v1/user');
        $response2->assertStatus(200);

        // Both should return the same user data
        $this->assertEquals(
            $response1->json('data.user.id'),
            $response2->json('data.user.id')
        );
    }
}
