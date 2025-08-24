<?php

namespace App\Http\Requests\Central;

use App\Rules\StrongPassword;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'min:2',
                'regex:/^[a-zA-Z\s\'-]+$/', // Only letters, spaces, hyphens, and apostrophes
            ],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                'unique:users,email',
            ],
            'password' => [
                'required',
                'string',
                'confirmed',
                new StrongPassword,
            ],
            'password_confirmation' => [
                'required',
                'string',
            ],
            'company_name' => [
                'required',
                'string',
                'max:255',
                'min:2',
            ],
            'domain' => [
                'required',
                'string',
                'max:63', // Domain label max length
                'min:3',
                'alpha_dash',
                'unique:domains,domain',
                function ($attribute, $value, $fail) {
                    // Additional domain validation
                    if (str_starts_with($value, '-') || str_ends_with($value, '-')) {
                        $fail('The domain cannot start or end with a hyphen.');
                    }

                    // Check against reserved domains
                    $reserved = ['api', 'www', 'mail', 'ftp', 'admin', 'support', 'help', 'docs'];
                    if (in_array(strtolower($value), $reserved)) {
                        $fail('The domain name is reserved and cannot be used.');
                    }
                },
            ],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'A name is required.',
            'name.min' => 'The name must be at least 2 characters.',
            'name.regex' => 'The name may only contain letters, spaces, hyphens, and apostrophes.',
            'email.required' => 'An email address is required.',
            'email.email' => 'Please provide a valid email address.',
            'email.unique' => 'This email address is already registered.',
            'password.required' => 'A password is required.',
            'password.confirmed' => 'The password confirmation does not match.',
            'password_confirmation.required' => 'Password confirmation is required.',
            'company_name.required' => 'A company name is required.',
            'company_name.min' => 'The company name must be at least 2 characters.',
            'domain.required' => 'A domain is required.',
            'domain.min' => 'The domain must be at least 3 characters.',
            'domain.alpha_dash' => 'The domain may only contain letters, numbers, and hyphens.',
            'domain.unique' => 'This domain is already taken.',
        ];
    }

    /**
     * Get the validated data from the request.
     */
    public function validated($key = null, $default = null): array
    {
        $validated = parent::validated();

        // Trim whitespace and normalize data
        $validated['name'] = trim($validated['name']);
        $validated['email'] = trim(strtolower($validated['email']));
        $validated['company_name'] = trim($validated['company_name']);
        $validated['domain'] = trim(strtolower($validated['domain']));

        return $validated;
    }

    /**
     * Handle a failed validation attempt.
     */
    protected function failedValidation(Validator $validator): void
    {
        $response = response()->json([
            'success' => false,
            'message' => 'The provided data was invalid.',
            'errors' => $validator->errors(),
            'meta' => [
                'timestamp' => now()->toISOString(),
                'error_code' => 'VALIDATION_FAILED',
            ],
        ], 422);

        throw new HttpResponseException($response);
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Additional validation against password similarity to email/name
        if ($this->has('password') && $this->has('email')) {
            $email = strtolower(trim($this->input('email')));
            $password = strtolower($this->input('password'));
            $emailLocal = explode('@', $email)[0];

            // Check if password is too similar to email
            if (str_contains($password, $emailLocal) || str_contains($emailLocal, $password)) {
                $this->merge([
                    'password_similarity_check' => false,
                ]);
            }
        }
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->has('password_similarity_check') && $this->input('password_similarity_check') === false) {
                $validator->errors()->add('password', 'The password cannot be similar to your email address.');
            }

            // Check password similarity to name
            if ($this->has('password') && $this->has('name')) {
                $name = strtolower(trim($this->input('name')));
                $password = strtolower($this->input('password'));

                $nameWords = explode(' ', $name);
                foreach ($nameWords as $word) {
                    if (strlen($word) > 3 && (str_contains($password, $word) || str_contains($word, $password))) {
                        $validator->errors()->add('password', 'The password cannot be similar to your name.');
                        break;
                    }
                }
            }
        });
    }
}
