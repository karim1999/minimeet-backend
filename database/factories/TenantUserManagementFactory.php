<?php

namespace Database\Factories;

use App\Models\Central\TenantUserManagement;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Central\TenantUserManagement>
 */
class TenantUserManagementFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = TenantUserManagement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'user_count' => fake()->numberBetween(1, 100),
            'active_users' => fake()->numberBetween(1, 50),
            'last_activity_at' => fake()->dateTimeBetween('-7 days'),
            'metadata' => [
                'total_meetings' => fake()->numberBetween(0, 500),
                'total_hours' => fake()->numberBetween(0, 1000),
                'average_meeting_duration' => fake()->numberBetween(15, 120),
            ],
        ];
    }

    /**
     * Indicate that the tenant has no recent activity.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'last_activity_at' => fake()->dateTimeBetween('-30 days', '-8 days'),
            'active_users' => 0,
        ]);
    }

    /**
     * Indicate that the tenant has high activity.
     */
    public function highActivity(): static
    {
        return $this->state(fn (array $attributes) => [
            'user_count' => fake()->numberBetween(50, 500),
            'active_users' => fake()->numberBetween(25, 250),
            'last_activity_at' => fake()->dateTimeBetween('-1 day'),
        ]);
    }
}
