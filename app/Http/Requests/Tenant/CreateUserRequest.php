<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;

class CreateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('tenant_sanctum')?->role === 'admin';
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
                new StrongPassword,
            ],
            'role' => [
                'required',
                'string',
                'in:owner,admin,manager,member',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'bio' => [
                'sometimes',
                'string',
                'max:1000',
            ],
            'avatar_url' => [
                'sometimes',
                'url',
                'max:500',
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
            'role.required' => 'Role is required.',
            'role.in' => 'Role must be one of: owner, admin, manager, member.',
            'bio.max' => 'Bio must not exceed 1000 characters.',
            'avatar_url.url' => 'Avatar URL must be a valid URL.',
        ];
    }

    protected function prepareForValidation(): void
    {
        // Set default active status if not provided
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
