<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'email' => [
                'required',
                'email:rfc',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'min:8',
                'max:255',
                'confirmed',
                new StrongPassword,
            ],
            'role' => [
                'sometimes',
                'string',
                'in:owner,admin,manager,member',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Full name is required.',
            'name.min' => 'Full name must be at least 2 characters long.',
            'email.required' => 'Email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            'role.in' => 'Role must be one of: owner, admin, manager, member.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default role to 'member' if not provided
        if (! $this->has('role')) {
            $this->merge(['role' => 'member']);
        }
    }
}
