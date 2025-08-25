<?php

namespace Database\Factories;

use App\Models\Tenant\TenantUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\TenantUser>
 */
class TenantUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = TenantUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => 'password', // password
            'role' => fake()->randomElement(['member', 'manager']),
            'department' => fake()->optional()->randomElement([
                'Engineering', 'Marketing', 'Sales', 'HR', 'Finance', 'Operations',
            ]),
            'title' => fake()->optional()->jobTitle(),
            'phone' => fake()->optional()->phoneNumber(),
            'avatar_url' => fake()->optional()->imageUrl(200, 200, 'people'),
            'is_active' => true,
            'last_login_at' => fake()->optional()->dateTimeBetween('-7 days'),
            'settings' => [],
            'metadata' => [],
            'remember_token' => \Str::random(10),
        ];
    }

    /**
     * Indicate that the user is the tenant owner.
     */
    public function owner(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'owner',
        ]);
    }

    /**
     * Indicate that the user is an admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Indicate that the user is a manager.
     */
    public function manager(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'manager',
        ]);
    }

    /**
     * Indicate that the user is a member.
     */
    public function member(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'member',
        ]);
    }

    /**
     * Indicate that the user is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
