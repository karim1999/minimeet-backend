<?php

namespace App\Http\Controllers\Central;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\LoginRequest;
use App\Http\Resources\Central\AuthTokenResource;
use App\Http\Resources\Central\UserResource;
use App\Http\Responses\ApiResponse;
use App\Services\Central\AuthenticationService;
use App\Services\Central\SessionService;
use App\Services\Central\TokenService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(
        private readonly AuthenticationService $authService,
        private readonly SessionService $sessionService,
        private readonly TokenService $tokenService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $result = $this->authService->authenticateUser($request, $validated);

        if (! $result['success']) {
            if ($result['lockout']) {
                return ApiResponse::error(
                    $result['message'],
                    ['email' => ['Account temporarily locked due to multiple failed attempts.']],
                    423,
                    'AUTH_LOCKED_OUT',
                    ['lockout_seconds_remaining' => $result['lockout_seconds_remaining']]
                );
            }

            return ApiResponse::error(
                $result['message'],
                ['email' => [$result['message']]],
                422,
                'AUTH_INVALID_CREDENTIALS'
            );
        }

        return ApiResponse::success(
            $result['message'],
            ['user' => new UserResource($result['user'])]
        );
    }

    public function logout(Request $request): JsonResponse
    {
        $this->sessionService->invalidateSession($request);

        return ApiResponse::success('Logged out successfully');
    }

    public function user(Request $request): JsonResponse
    {
        $user = $request->user();

        return ApiResponse::success(
            'User retrieved successfully',
            ['user' => new UserResource($user)]
        );
    }

    /**
     * Create a new API token for the authenticated user.
     */
    public function createToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['array'],
            'abilities.*' => ['string'],
            'expires_at' => ['nullable', 'date', 'after:now'],
        ]);

        $user = $request->user();
        $result = $this->tokenService->createToken(
            $user,
            $validated['name'],
            $validated['abilities'] ?? ['central:*'],
            $validated['expires_at'] ?? null
        );

        if (! $result['success']) {
            return ApiResponse::error(
                $result['message'],
                ['token' => [$result['error']]],
                422,
                'TOKEN_CREATE_FAILED'
            );
        }

        return ApiResponse::created(
            $result['message'],
            [
                'token' => $result['token'],
                'plain_text_token' => $result['plain_text_token'],
            ]
        );
    }

    /**
     * List all active tokens for the authenticated user.
     */
    public function listTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        $tokens = $this->tokenService->listUserTokens($user);

        return ApiResponse::collection(
            AuthTokenResource::collection($tokens),
            $tokens->count(),
            'Tokens retrieved successfully'
        );
    }

    /**
     * Revoke a specific token.
     */
    public function revokeToken(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'token_id' => ['required', 'integer'],
        ]);

        $user = $request->user();
        $result = $this->tokenService->revokeToken($user, $validated['token_id']);

        if (! $result['success']) {
            return ApiResponse::error(
                $result['message'],
                ['token' => [$result['error']]],
                422,
                'TOKEN_REVOKE_FAILED'
            );
        }

        return ApiResponse::success($result['message']);
    }

    /**
     * Revoke all tokens for the authenticated user.
     */
    public function revokeAllTokens(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->tokenService->revokeAllTokens($user);

        return ApiResponse::success(
            $result['message'],
            ['revoked_count' => $result['revoked_count']]
        );
    }

    /**
     * Revoke the current token being used.
     */
    public function revokeCurrentToken(Request $request): JsonResponse
    {
        $user = $request->user();
        $result = $this->tokenService->revokeCurrentToken($user);

        if (! $result['success']) {
            return ApiResponse::error($result['message']);
        }

        return ApiResponse::success($result['message']);
    }
}
