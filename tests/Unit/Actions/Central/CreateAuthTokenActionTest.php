<?php

namespace Tests\Unit\Actions\Central;

use App\Actions\Central\CreateAuthTokenAction;
use App\Models\User;
use Laravel\Sanctum\NewAccessToken;
use Laravel\Sanctum\PersonalAccessToken;
use Mockery;
use Tests\TestCase;

class CreateAuthTokenActionTest extends TestCase
{
    private CreateAuthTokenAction $action;

    public function setUp(): void
    {
        parent::setUp();
        $this->action = new CreateAuthTokenAction;
    }

    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_execute_creates_token_successfully(): void
    {
        $user = Mockery::mock(User::class);
        $personalAccessToken = Mockery::mock(PersonalAccessToken::class);
        $newAccessToken = Mockery::mock(NewAccessToken::class);

        $personalAccessToken->shouldReceive('getAttribute')->with('expires_at')->andReturn(null);
        $personalAccessToken->shouldReceive('setAttribute')->with('expires_at', Mockery::any());
        $personalAccessToken->shouldReceive('save')->once();

        $newAccessToken->accessToken = $personalAccessToken;
        $newAccessToken->plainTextToken = 'plain-text-token';

        $user->shouldReceive('createToken')
            ->with('Test Token', ['central:*'])
            ->andReturn($newAccessToken);

        $user->shouldReceive('tokens->count')->andReturn(2);
        $user->shouldReceive('tokens->where->exists')->andReturn(false);

        $result = $this->action->execute($user, 'Test Token', ['central:*'], '2024-12-31 23:59:59');

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('plain_text_token', $result);
        $this->assertEquals('plain-text-token', $result['plain_text_token']);
    }

    public function test_execute_generates_name_when_empty(): void
    {
        $user = Mockery::mock(User::class);
        $personalAccessToken = Mockery::mock(PersonalAccessToken::class);
        $newAccessToken = Mockery::mock(NewAccessToken::class);

        $newAccessToken->accessToken = $personalAccessToken;
        $newAccessToken->plainTextToken = 'plain-text-token';

        $user->shouldReceive('createToken')
            ->with(Mockery::pattern('/API Token \d{4}-\d{2}-\d{2}/'), ['*'])
            ->andReturn($newAccessToken);

        $user->shouldReceive('tokens->count')->andReturn(1);
        $user->shouldReceive('tokens->where->exists')->andReturn(false);

        $result = $this->action->execute($user, '', ['*']);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('token', $result);
        $this->assertArrayHasKey('plain_text_token', $result);
    }

    public function test_create_central_token_uses_central_abilities(): void
    {
        $user = Mockery::mock(User::class);
        $personalAccessToken = Mockery::mock(PersonalAccessToken::class);
        $newAccessToken = Mockery::mock(NewAccessToken::class);

        $newAccessToken->accessToken = $personalAccessToken;
        $newAccessToken->plainTextToken = 'central-token';

        $user->shouldReceive('createToken')
            ->with('Central Token', ['central:*'])
            ->andReturn($newAccessToken);

        $user->shouldReceive('tokens->count')->andReturn(1);
        $user->shouldReceive('tokens->where->exists')->andReturn(false);

        $result = $this->action->createCentralToken($user, 'Central Token');

        $this->assertIsArray($result);
        $this->assertEquals('central-token', $result['plain_text_token']);
    }

    public function test_create_long_lived_token_sets_expiration(): void
    {
        $user = Mockery::mock(User::class);
        $personalAccessToken = Mockery::mock(PersonalAccessToken::class);
        $newAccessToken = Mockery::mock(NewAccessToken::class);

        $personalAccessToken->shouldReceive('setAttribute')
            ->with('expires_at', Mockery::pattern('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/'))
            ->once();
        $personalAccessToken->shouldReceive('save')->once();

        $newAccessToken->accessToken = $personalAccessToken;
        $newAccessToken->plainTextToken = 'long-lived-token';

        $user->shouldReceive('createToken')
            ->with('Long Lived Token', ['central:*'])
            ->andReturn($newAccessToken);

        $user->shouldReceive('tokens->count')->andReturn(1);
        $user->shouldReceive('tokens->where->exists')->andReturn(false);

        $result = $this->action->createLongLivedToken($user, 'Long Lived Token');

        $this->assertIsArray($result);
        $this->assertEquals('long-lived-token', $result['plain_text_token']);
    }

    public function test_create_short_lived_token_sets_expiration(): void
    {
        $user = Mockery::mock(User::class);
        $personalAccessToken = Mockery::mock(PersonalAccessToken::class);
        $newAccessToken = Mockery::mock(NewAccessToken::class);

        $personalAccessToken->shouldReceive('setAttribute')
            ->with('expires_at', Mockery::pattern('/\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/'))
            ->once();
        $personalAccessToken->shouldReceive('save')->once();

        $newAccessToken->accessToken = $personalAccessToken;
        $newAccessToken->plainTextToken = 'short-lived-token';

        $user->shouldReceive('createToken')
            ->with('Short Lived Token', ['central:*'])
            ->andReturn($newAccessToken);

        $user->shouldReceive('tokens->count')->andReturn(1);
        $user->shouldReceive('tokens->where->exists')->andReturn(false);

        $result = $this->action->createShortLivedToken($user, 'Short Lived Token');

        $this->assertIsArray($result);
        $this->assertEquals('short-lived-token', $result['plain_text_token']);
    }
}
