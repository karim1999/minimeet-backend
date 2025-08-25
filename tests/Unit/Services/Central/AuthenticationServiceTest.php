<?php

namespace Tests\Unit\Services\Central;

use App\Logging\Central\AuthenticationLogger;
use App\Models\Central\CentralUser;
use App\Services\Central\AuthenticationService;
use App\Services\Central\LoginAttemptService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Mockery;
use Tests\TestCase;

class AuthenticationServiceTest extends TestCase
{
    use DatabaseTransactions;

    private AuthenticationService $service;

    private LoginAttemptService $loginAttemptService;

    private AuthenticationLogger $authLogger;

    public function setUp(): void
    {
        parent::setUp();

        $this->loginAttemptService = Mockery::mock(LoginAttemptService::class);
        $this->authLogger = Mockery::mock(AuthenticationLogger::class);

        $this->service = new AuthenticationService(
            $this->loginAttemptService,
            $this->authLogger
        );
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_authenticate_user_returns_lockout_when_locked_out(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('192.168.1.1');

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];

        $this->loginAttemptService->shouldReceive('isLockedOut')
            ->with('test@example.com', '192.168.1.1')
            ->andReturn(true);

        $this->loginAttemptService->shouldReceive('getLockoutTimeRemaining')
            ->with('test@example.com', '192.168.1.1')
            ->andReturn(300);

        $this->authLogger->shouldReceive('logFailedLogin')
            ->once()
            ->with('test@example.com', $request, 'account_locked');

        $result = $this->service->authenticateUser($request, $credentials);

        $this->assertFalse($result['success']);
        $this->assertTrue($result['lockout']);
        $this->assertEquals(300, $result['lockout_seconds_remaining']);
    }

    public function test_verify_credentials_returns_true_for_valid_credentials(): void
    {
        // Create a real user with known credentials
        $email = 'auth-test-valid-' . time() . '@example.com';
        $user = CentralUser::factory()->create([
            'email' => $email,
            'password' => Hash::make('password123'),
        ]);

        $result = $this->service->verifyCredentials($email, 'password123');

        $this->assertTrue($result);
    }

    public function test_verify_credentials_returns_false_for_invalid_credentials(): void
    {
        // Create a real user with known credentials
        $email = 'auth-test-invalid-' . time() . '@example.com';
        $user = CentralUser::factory()->create([
            'email' => $email,
            'password' => Hash::make('password123'),
        ]);

        $result = $this->service->verifyCredentials($email, 'wrongpassword');

        $this->assertFalse($result);
    }

    public function test_verify_credentials_returns_false_for_nonexistent_user(): void
    {
        // Test with an email that doesn't exist in the database
        $result = $this->service->verifyCredentials('nonexistent@example.com', 'password');

        $this->assertFalse($result);
    }

    public function test_validate_authentication_attempt_returns_errors_for_invalid_email(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('192.168.1.1');
        $credentials = ['email' => 'invalid-email', 'password' => 'password'];

        $this->loginAttemptService->shouldReceive('isLockedOut')
            ->with('invalid-email', '192.168.1.1')
            ->andReturn(false);

        $result = $this->service->validateAuthenticationAttempt($request, $credentials);

        $this->assertArrayHasKey('email', $result);
        $this->assertContains('Invalid email format.', $result['email']);
    }

    public function test_validate_authentication_attempt_returns_errors_for_empty_password(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('192.168.1.1');
        $credentials = ['email' => 'test@example.com', 'password' => ''];

        $this->loginAttemptService->shouldReceive('isLockedOut')
            ->with('test@example.com', '192.168.1.1')
            ->andReturn(false);

        $result = $this->service->validateAuthenticationAttempt($request, $credentials);

        $this->assertArrayHasKey('password', $result);
        $this->assertContains('Password is required.', $result['password']);
    }

    public function test_validate_authentication_attempt_returns_lockout_error(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('192.168.1.1');

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];

        $this->loginAttemptService->shouldReceive('isLockedOut')
            ->with('test@example.com', '192.168.1.1')
            ->andReturn(true);

        $result = $this->service->validateAuthenticationAttempt($request, $credentials);

        $this->assertArrayHasKey('general', $result);
        $this->assertContains('Account temporarily locked due to multiple failed attempts.', $result['general']);
    }

    public function test_validate_authentication_attempt_returns_empty_for_valid_input(): void
    {
        $request = Mockery::mock(Request::class);
        $request->shouldReceive('ip')->andReturn('192.168.1.1');

        $credentials = ['email' => 'test@example.com', 'password' => 'password'];

        $this->loginAttemptService->shouldReceive('isLockedOut')
            ->with('test@example.com', '192.168.1.1')
            ->andReturn(false);

        $result = $this->service->validateAuthenticationAttempt($request, $credentials);

        $this->assertEmpty($result);
    }
}
