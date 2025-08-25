<?php

declare(strict_types=1);

namespace App\Http\Requests\Tenant;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $user = $this->user('tenant_sanctum');

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
                Rule::unique('tenant_users', 'email')->ignore($user?->id),
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
            'bio.max' => 'Bio must not exceed 1000 characters.',
            'avatar_url.url' => 'Avatar URL must be a valid URL.',
        ];
    }
}
