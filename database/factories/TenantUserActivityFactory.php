<?php

namespace Database\Factories;

use App\Models\Tenant\TenantUser;
use App\Models\Tenant\TenantUserActivity;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant\TenantUserActivity>
 */
class TenantUserActivityFactory extends Factory
{
    protected $model = TenantUserActivity::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $actions = ['login', 'logout', 'user_updated', 'password_changed', 'profile_viewed', 'settings_updated'];
        $action = $this->faker->randomElement($actions);

        return [
            'user_id' => TenantUser::factory(),
            'action' => $action,
            'description' => $this->faker->sentence(),
            'model_type' => null,
            'model_id' => null,
            'metadata' => [
                'browser' => $this->faker->userAgent(),
                'timestamp' => now()->toISOString(),
            ],
            'ip_address' => $this->faker->ipv4(),
            'created_at' => $this->faker->dateTimeBetween('-30 days', 'now'),
            'updated_at' => now(),
        ];
    }

    /**
     * Create a login activity.
     */
    public function login(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'login',
            'description' => 'User logged in',
        ]);
    }

    /**
     * Create a logout activity.
     */
    public function logout(): static
    {
        return $this->state(fn (array $attributes) => [
            'action' => 'logout',
            'description' => 'User logged out',
        ]);
    }

    /**
     * Create an activity for a specific user.
     */
    public function forUser(int $userId): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $userId,
        ]);
    }

    /**
     * Create a recent activity.
     */
    public function recent(): static
    {
        return $this->state(fn (array $attributes) => [
            'created_at' => $this->faker->dateTimeBetween('-24 hours', 'now'),
        ]);
    }
}
