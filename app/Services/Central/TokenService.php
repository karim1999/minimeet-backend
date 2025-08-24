<?php

namespace App\Services\Central;

use App\Actions\Central\CreateAuthTokenAction;
use App\Actions\Central\RevokeAuthTokenAction;
use App\Http\Resources\Central\AuthTokenResource;
use App\Logging\Central\AuthenticationLogger;
use App\Models\User;
use Illuminate\Support\Collection;
use Laravel\Sanctum\PersonalAccessToken;

class TokenService
{
    public function __construct(
        private readonly CreateAuthTokenAction $createTokenAction,
        private readonly RevokeAuthTokenAction $revokeTokenAction,
        private readonly AuthenticationLogger $authLogger
    ) {}

    /**
     * Create a new token for the user.
     */
    public function createToken(
        User $user,
        string $name,
        array $abilities = ['central:*'],
        ?string $expiresAt = null
    ): array {
        try {
            $result = $this->createTokenAction->execute($user, $name, $abilities, $expiresAt);

            return [
                'success' => true,
                'token' => new AuthTokenResource($result['token']),
                'plain_text_token' => $result['plain_text_token'],
                'message' => 'Token created successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to create token',
            ];
        }
    }

    /**
     * List all tokens for a user.
     */
    public function listUserTokens(User $user): Collection
    {
        return $user->tokens()
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get token with additional metadata.
     */
    public function getTokenWithMetadata(User $user, int $tokenId): ?array
    {
        $token = $user->tokens()->find($tokenId);

        if (! $token) {
            return null;
        }

        return [
            'token' => new AuthTokenResource($token),
            'metadata' => [
                'is_current' => $this->isCurrentToken($token),
                'usage_stats' => $this->getTokenUsageStats($token),
                'security_info' => $this->getTokenSecurityInfo($token),
            ],
        ];
    }

    /**
     * Revoke a specific token.
     */
    public function revokeToken(User $user, int $tokenId): array
    {
        try {
            $this->revokeTokenAction->revokeToken($user, $tokenId);

            return [
                'success' => true,
                'message' => 'Token revoked successfully',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'message' => 'Failed to revoke token',
            ];
        }
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllTokens(User $user): array
    {
        $count = $this->revokeTokenAction->revokeAllTokens($user);

        return [
            'success' => true,
            'revoked_count' => $count,
            'message' => 'All tokens revoked successfully',
        ];
    }

    /**
     * Revoke the current token being used.
     */
    public function revokeCurrentToken(User $user): array
    {
        $currentToken = $user->currentAccessToken();

        if (! $currentToken) {
            return [
                'success' => false,
                'message' => 'No current token to revoke',
            ];
        }

        $currentToken->delete();

        return [
            'success' => true,
            'message' => 'Current token revoked successfully',
        ];
    }

    /**
     * Clean up expired tokens.
     */
    public function cleanupExpiredTokens(?User $user = null): array
    {
        if ($user) {
            $count = $this->revokeTokenAction->revokeExpiredTokens($user);

            return [
                'success' => true,
                'expired_tokens_removed' => $count,
                'user_id' => $user->id,
            ];
        }

        // Clean up expired tokens for all users
        $count = PersonalAccessToken::where('expires_at', '<', now())->delete();

        return [
            'success' => true,
            'expired_tokens_removed' => $count,
            'scope' => 'all_users',
        ];
    }

    /**
     * Get token statistics.
     */
    public function getTokenStatistics(?User $user = null): array
    {
        if ($user) {
            return $this->getUserTokenStatistics($user);
        }

        return $this->getGlobalTokenStatistics();
    }

    /**
     * Validate token abilities.
     */
    public function validateTokenAbilities(array $abilities): array
    {
        $validAbilities = [
            'central:*',
            'central:read',
            'central:write',
            'central:admin',
            'tenant:manage',
        ];

        $errors = [];

        foreach ($abilities as $ability) {
            if (! in_array($ability, $validAbilities) && $ability !== '*') {
                $errors[] = "Invalid ability: $ability";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'valid_abilities' => $validAbilities,
        ];
    }

    /**
     * Check if a token is the current one being used.
     */
    private function isCurrentToken(PersonalAccessToken $token): bool
    {
        $user = $token->tokenable;
        $currentToken = $user->currentAccessToken();

        return $currentToken && $currentToken->id === $token->id;
    }

    /**
     * Get usage statistics for a token.
     */
    private function getTokenUsageStats(PersonalAccessToken $token): array
    {
        return [
            'created_at' => $token->created_at,
            'last_used_at' => $token->last_used_at,
            'expires_at' => $token->expires_at,
            'is_expired' => $token->expires_at && $token->expires_at->isPast(),
            'days_since_creation' => $token->created_at->diffInDays(now()),
            'days_since_last_use' => $token->last_used_at ? $token->last_used_at->diffInDays(now()) : null,
        ];
    }

    /**
     * Get security information for a token.
     */
    private function getTokenSecurityInfo(PersonalAccessToken $token): array
    {
        return [
            'abilities' => $token->abilities ?? ['*'],
            'can_do_everything' => in_array('*', $token->abilities ?? []),
            'has_admin_access' => in_array('central:admin', $token->abilities ?? []),
            'token_length' => strlen($token->token ?? ''),
        ];
    }

    /**
     * Get token statistics for a specific user.
     */
    private function getUserTokenStatistics(User $user): array
    {
        $tokens = $user->tokens();

        return [
            'total_tokens' => $tokens->count(),
            'active_tokens' => $tokens->where('expires_at', '>', now())->orWhereNull('expires_at')->count(),
            'expired_tokens' => $tokens->where('expires_at', '<', now())->count(),
            'recently_used_tokens' => $tokens->where('last_used_at', '>', now()->subDays(7))->count(),
            'oldest_token_age_days' => $tokens->min('created_at') ? now()->diffInDays($tokens->min('created_at')) : 0,
        ];
    }

    /**
     * Get global token statistics.
     */
    private function getGlobalTokenStatistics(): array
    {
        return [
            'total_tokens' => PersonalAccessToken::count(),
            'active_tokens' => PersonalAccessToken::where('expires_at', '>', now())->orWhereNull('expires_at')->count(),
            'expired_tokens' => PersonalAccessToken::where('expires_at', '<', now())->count(),
            'recently_used_tokens' => PersonalAccessToken::where('last_used_at', '>', now()->subDays(7))->count(),
            'total_users_with_tokens' => PersonalAccessToken::distinct('tokenable_id')->count(),
        ];
    }
}
