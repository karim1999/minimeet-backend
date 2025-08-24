<?php

namespace Tests\Unit\Rules;

use App\Rules\StrongPassword;
use Tests\TestCase;

class StrongPasswordTest extends TestCase
{
    private StrongPassword $rule;

    public function setUp(): void
    {
        parent::setUp();
        $this->rule = new StrongPassword;
    }

    public function test_validates_password_minimum_length(): void
    {
        $this->assertValidationFails('short', 'must be at least 8 characters');
        $this->assertValidationPasses('LongEnough4!');
    }

    public function test_validates_password_maximum_length(): void
    {
        $longPassword = str_repeat('a', 256).'A1!';
        $this->assertValidationFails($longPassword, 'must not exceed 255 characters');
    }

    public function test_validates_uppercase_requirement(): void
    {
        $this->assertValidationFails('lowercase8!', 'must contain at least one uppercase letter');
        $this->assertValidationPasses('Uppercase8!');
    }

    public function test_validates_lowercase_requirement(): void
    {
        $this->assertValidationFails('UPPERCASE8!', 'must contain at least one lowercase letter');
        $this->assertValidationPasses('Lowercase8!');
    }

    public function test_validates_number_requirement(): void
    {
        $this->assertValidationFails('NoNumbers!A', 'must contain at least one number');
        $this->assertValidationPasses('WithNumber1!');
    }

    public function test_validates_special_character_requirement(): void
    {
        $this->assertValidationFails('NoSpecial8A', 'must contain at least one special character');
        $this->assertValidationPasses('Special8A!');
    }

    public function test_rejects_common_passwords(): void
    {
        // Test passwords that meet all requirements but are in the common list
        $this->assertValidationFails('Pass@123', 'is too common');
        $this->assertValidationFails('Password@123', 'is too common');
    }

    public function test_rejects_repeated_characters(): void
    {
        $this->assertValidationFails('Aaaaa1947!', 'more than 3 repeated characters');
        $this->assertValidationPasses('Aaa1947!');
    }

    public function test_rejects_numeric_sequences(): void
    {
        $this->assertValidationFails('Test123!', 'sequential patterns');
        $this->assertValidationFails('Test987!', 'sequential patterns');
        $this->assertValidationPasses('Test145!');
    }

    public function test_rejects_alphabetic_sequences(): void
    {
        $this->assertValidationFails('Testabc1!', 'sequential patterns');
        $this->assertValidationFails('Testzyx1!', 'sequential patterns');
        $this->assertValidationPasses('Testaxz1!');
    }

    public function test_validates_valid_strong_password(): void
    {
        $validPasswords = [
            'MyStr0ng!Pass',
            'C0mpl3x$Password',
            'S3cur3#Testing',
            'V@lid8Password',
        ];

        foreach ($validPasswords as $password) {
            $this->assertValidationPasses($password);
        }
    }

    public function test_handles_non_string_input(): void
    {
        $this->assertValidationFails(123, 'must be a string');
        $this->assertValidationFails(['password'], 'must be a string');
        $this->assertValidationFails(null, 'must be a string');
    }

    private function assertValidationPasses(mixed $value): void
    {
        $failed = false;
        $this->rule->validate('password', $value, function () use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, "Expected validation to pass for: {$value}");
    }

    private function assertValidationFails(mixed $value, string $expectedMessage): void
    {
        $failed = false;
        $actualMessage = '';

        $this->rule->validate('password', $value, function ($message) use (&$failed, &$actualMessage) {
            $failed = true;
            $actualMessage = $message;
        });

        $valueString = is_array($value) ? 'array' : (string) $value;
        $this->assertTrue($failed, "Expected validation to fail for: {$valueString}");
        $this->assertStringContainsString($expectedMessage, $actualMessage);
    }
}
