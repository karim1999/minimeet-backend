<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use App\Rules\StrongPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('tenant_sanctum')?->role === 'admin';
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        return [
            'name' => [
                'sometimes',
                'string',
                'max:255',
                'min:2',
            ],
            'email' => [
                'sometimes',
                'email:rfc',
                'max:255',
                Rule::unique('tenant_users', 'email')->ignore($userId),
            ],
            'password' => [
                'sometimes',
                'string',
                'min:8',
                'max:255',
                new StrongPassword,
            ],
            'role' => [
                'sometimes',
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
                'nullable',
            ],
            'avatar_url' => [
                'sometimes',
                'url',
                'max:500',
                'nullable',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.min' => 'Full name must be at least 2 characters long.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.min' => 'Password must be at least 8 characters long.',
            'role.in' => 'Role must be one of: admin, manager, user.',
            'bio.max' => 'Bio must not exceed 1000 characters.',
            'avatar_url.url' => 'Avatar URL must be a valid URL.',
        ];
    }
}
