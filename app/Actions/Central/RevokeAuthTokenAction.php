<?php

namespace App\Actions\Central;

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

class RevokeAuthTokenAction
{
    /**
     * Revoke a specific token by ID.
     */
    public function revokeToken(User $user, int $tokenId): bool
    {
        $token = $user->tokens()->find($tokenId);

        if (! $token) {
            throw new \InvalidArgumentException("Token with ID $tokenId not found for this user.");
        }

        return $token->delete();
    }

    /**
     * Revoke a specific token by name.
     */
    public function revokeTokenByName(User $user, string $tokenName): bool
    {
        $token = $user->tokens()->where('name', $tokenName)->first();

        if (! $token) {
            throw new \InvalidArgumentException("Token with name '$tokenName' not found for this user.");
        }

        return $token->delete();
    }

    /**
     * Revoke all tokens for a user.
     */
    public function revokeAllTokens(User $user): int
    {
        return $user->tokens()->delete();
    }

    /**
     * Revoke all tokens except the current one.
     */
    public function revokeOtherTokens(User $user, PersonalAccessToken $currentToken): int
    {
        return $user->tokens()
            ->where('id', '!=', $currentToken->id)
            ->delete();
    }

    /**
     * Revoke expired tokens for a user.
     */
    public function revokeExpiredTokens(User $user): int
    {
        return $user->tokens()
            ->where('expires_at', '<', now())
            ->delete();
    }

    /**
     * Revoke tokens older than specified days.
     */
    public function revokeOldTokens(User $user, int $days = 30): int
    {
        $cutoffDate = now()->subDays($days);

        return $user->tokens()
            ->where('created_at', '<', $cutoffDate)
            ->delete();
    }

    /**
     * Revoke tokens with specific abilities.
     */
    public function revokeTokensWithAbilities(User $user, array $abilities): int
    {
        $tokens = $user->tokens()->get();
        $revokedCount = 0;

        foreach ($tokens as $token) {
            $tokenAbilities = $token->abilities ?? [];

            // Check if token has any of the specified abilities
            if (array_intersect($abilities, $tokenAbilities)) {
                $token->delete();
                $revokedCount++;
            }
        }

        return $revokedCount;
    }

    /**
     * Cleanup tokens - remove expired and old tokens.
     */
    public function cleanup(User $user): array
    {
        $expiredCount = $this->revokeExpiredTokens($user);
        $oldCount = $this->revokeOldTokens($user, 90); // Tokens older than 90 days

        return [
            'expired_tokens_removed' => $expiredCount,
            'old_tokens_removed' => $oldCount,
            'total_removed' => $expiredCount + $oldCount,
        ];
    }
}
