<?php

namespace App\Actions\Central;

use App\Models\User;

class CreateAuthTokenAction
{
    /**
     * Create a new personal access token for the user.
     */
    public function execute(
        User $user,
        string $name,
        array $abilities = ['*'],
        ?string $expiresAt = null
    ): array {
        // Note: In production, additional checks should ensure this is only called for central users
        // This action should only be used in central authentication context

        // Generate a unique token name if not provided
        if (empty($name)) {
            $name = 'API Token '.now()->format('Y-m-d H:i:s');
        }

        // Validate token creation
        $this->validateTokenCreation($user, $name, $abilities);

        // Create the token
        $newAccessToken = $user->createToken($name, $abilities);

        // Set expiration if provided
        if ($expiresAt) {
            $newAccessToken->accessToken->expires_at = $expiresAt;
            $newAccessToken->accessToken->save();
        }

        return [
            'token' => $newAccessToken->accessToken,
            'plain_text_token' => $newAccessToken->plainTextToken,
        ];
    }

    /**
     * Create a token with specific abilities for central authentication.
     */
    public function createCentralToken(
        User $user,
        string $name,
        ?string $expiresAt = null
    ): array {
        return $this->execute($user, $name, ['central:*'], $expiresAt);
    }

    /**
     * Create a long-lived token (30 days).
     */
    public function createLongLivedToken(
        User $user,
        string $name
    ): array {
        $expiresAt = now()->addDays(30)->toDateTimeString();

        return $this->createCentralToken($user, $name, $expiresAt);
    }

    /**
     * Create a short-lived token (1 hour).
     */
    public function createShortLivedToken(
        User $user,
        string $name
    ): array {
        $expiresAt = now()->addHour()->toDateTimeString();

        return $this->createCentralToken($user, $name, $expiresAt);
    }

    /**
     * Validate token creation parameters.
     */
    private function validateTokenCreation(
        User $user,
        string $name,
        array $abilities
    ): void {
        // Check user token limits
        $existingTokens = $user->tokens()->count();
        $maxTokens = config('sanctum.max_tokens_per_user', 10);

        if ($existingTokens >= $maxTokens) {
            throw new \Exception("Maximum number of tokens ($maxTokens) reached for this user.");
        }

        // Validate token name
        if (strlen($name) > 255) {
            throw new \InvalidArgumentException('Token name cannot exceed 255 characters.');
        }

        // Check for duplicate token names
        if ($user->tokens()->where('name', $name)->exists()) {
            throw new \InvalidArgumentException("A token with the name '$name' already exists.");
        }

        // Validate abilities
        $validAbilities = [
            'central:*',
            'central:read',
            'central:write',
            'central:admin',
            'tenant:manage',
        ];

        foreach ($abilities as $ability) {
            if (! in_array($ability, $validAbilities) && $ability !== '*') {
                throw new \InvalidArgumentException("Invalid ability: $ability");
            }
        }
    }
}
