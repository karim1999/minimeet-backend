<?php

namespace Database\Factories;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tenant>
 */
class TenantFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\Illuminate\Database\Eloquent\Model>
     */
    protected $model = Tenant::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();
        $slug = \Str::slug($name).'-'.fake()->unique()->numberBetween(1000, 9999);

        return [
            'id' => $slug,
            'data' => [
                'name' => $name,
                'created_by' => fake()->name(),
                'plan' => fake()->randomElement(['basic', 'pro', 'enterprise']),
                'status' => 'active',
            ],
        ];
    }

    /**
     * Indicate that the tenant is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'data' => array_merge($attributes['data'], [
                'status' => 'inactive',
            ]),
        ]);
    }

    /**
     * Indicate that the tenant is on enterprise plan.
     */
    public function enterprise(): static
    {
        return $this->state(fn (array $attributes) => [
            'data' => array_merge($attributes['data'], [
                'plan' => 'enterprise',
            ]),
        ]);
    }
}
