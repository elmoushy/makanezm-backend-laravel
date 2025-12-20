<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DiscountCode>
 */
class DiscountCodeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->unique()->lexify('????##')),
            'discount_percent' => fake()->randomElement([5, 10, 15, 20, 25, 30]),
            'is_active' => true,
            'valid_from' => null,
            'valid_until' => null,
            'usage_limit' => null,
            'times_used' => 0,
        ];
    }

    /**
     * Indicate that the code is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the code has a usage limit.
     */
    public function limited(int $limit = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'usage_limit' => $limit,
        ]);
    }

    /**
     * Indicate that the code has a validity period.
     */
    public function withValidity(): static
    {
        return $this->state(fn (array $attributes) => [
            'valid_from' => now(),
            'valid_until' => now()->addMonths(3),
        ]);
    }
}
