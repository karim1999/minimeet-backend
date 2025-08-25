<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class ResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'token' => [
                'required',
                'string',
                'max:255',
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                'exists:tenant_users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                new StrongPassword,
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'token.required' => 'Reset token is required.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.exists' => 'No account found with this email address.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
        ];
    }
}
