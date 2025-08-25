<?php

namespace Database\Factories;

use App\Models\Central\CentralUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\CentralUser>
 */
class CentralUserFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = CentralUser::class;

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
            'role' => fake()->randomElement(['admin', 'support']),
            'is_central' => true,
            'last_login_at' => fake()->optional()->dateTimeBetween('-30 days'),
            'metadata' => [],
            'remember_token' => \Str::random(10),
        ];
    }

    /**
     * Indicate that the user is a super admin.
     */
    public function superAdmin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'super_admin',
        ]);
    }

    /**
     * Indicate that the user is a regular admin.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
        ]);
    }

    /**
     * Indicate that the user is support staff.
     */
    public function support(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'support',
        ]);
    }
}
