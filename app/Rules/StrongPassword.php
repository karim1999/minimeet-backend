<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class StrongPassword implements ValidationRule
{
    /**
     * Common passwords to reject
     */
    private array $commonPasswords = [
        'password', 'password123', '123456', '12345678', 'qwerty', 'abc123',
        'password1', 'admin', 'letmein', 'welcome', '123456789', 'password@123',
        'Pass@123', 'admin123', 'root', 'user', 'test', 'guest', '1234567890',
    ];

    /**
     * Run the validation rule.
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            $fail('The :attribute must be a string.');

            return;
        }

        // Check minimum length
        if (strlen($value) < 8) {
            $fail('The :attribute must be at least 8 characters long.');

            return;
        }

        // Check maximum length (security against long password DoS)
        if (strlen($value) > 255) {
            $fail('The :attribute must not exceed 255 characters.');

            return;
        }

        // Check for at least one uppercase letter
        if (! preg_match('/[A-Z]/', $value)) {
            $fail('The :attribute must contain at least one uppercase letter.');

            return;
        }

        // Check for at least one lowercase letter
        if (! preg_match('/[a-z]/', $value)) {
            $fail('The :attribute must contain at least one lowercase letter.');

            return;
        }

        // Check for at least one number
        if (! preg_match('/[0-9]/', $value)) {
            $fail('The :attribute must contain at least one number.');

            return;
        }

        // Check for at least one special character
        if (! preg_match('/[^A-Za-z0-9]/', $value)) {
            $fail('The :attribute must contain at least one special character.');

            return;
        }

        // Check against common passwords
        if (in_array(strtolower($value), array_map('strtolower', $this->commonPasswords))) {
            $fail('The :attribute is too common. Please choose a more secure password.');

            return;
        }

        // Check for repeated characters (more than 3 in a row)
        if (preg_match('/(.)\1{3,}/', $value)) {
            $fail('The :attribute must not contain more than 3 repeated characters in a row.');

            return;
        }

        // Check for sequential patterns (123, abc, etc.)
        if ($this->hasSequentialPattern($value)) {
            $fail('The :attribute must not contain sequential patterns (like 123 or abc).');

            return;
        }
    }

    /**
     * Check for sequential patterns in the password
     */
    private function hasSequentialPattern(string $password): bool
    {
        $password = strtolower($password);

        // Check for numeric sequences (123, 456, etc.)
        for ($i = 0; $i < strlen($password) - 2; $i++) {
            if (is_numeric($password[$i]) &&
                is_numeric($password[$i + 1]) &&
                is_numeric($password[$i + 2])) {

                $first = (int) $password[$i];
                $second = (int) $password[$i + 1];
                $third = (int) $password[$i + 2];

                if (($second == $first + 1) && ($third == $second + 1)) {
                    return true; // Ascending sequence
                }

                if (($second == $first - 1) && ($third == $second - 1)) {
                    return true; // Descending sequence
                }
            }
        }

        // Check for alphabetic sequences (abc, xyz, etc.)
        for ($i = 0; $i < strlen($password) - 2; $i++) {
            if (ctype_alpha($password[$i]) &&
                ctype_alpha($password[$i + 1]) &&
                ctype_alpha($password[$i + 2])) {

                $first = ord($password[$i]);
                $second = ord($password[$i + 1]);
                $third = ord($password[$i + 2]);

                if (($second == $first + 1) && ($third == $second + 1)) {
                    return true; // Ascending sequence
                }

                if (($second == $first - 1) && ($third == $second - 1)) {
                    return true; // Descending sequence
                }
            }
        }

        return false;
    }
}
