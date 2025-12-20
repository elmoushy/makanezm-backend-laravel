<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Product>
 */
class ProductFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(3, true),
            'description' => fake()->paragraph(),
            'type' => fake()->randomElement(['phones', 'laptops', 'car parts', 'tires', 'electronics']),
            'price' => fake()->randomFloat(2, 100, 50000),
            'stock_quantity' => fake()->numberBetween(10, 100),
            'in_stock' => true,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the product is out of stock.
     */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
            'in_stock' => false,
        ]);
    }

    /**
     * Indicate that the product is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create with default payment options.
     */
    public function withPaymentOptions(): static
    {
        return $this->afterCreating(function ($product) {
            // Cash option
            $product->allPaymentOptions()->create([
                'type' => 'cash',
                'label' => 'Cash',
                'is_active' => true,
            ]);

            // Installment option
            $product->allPaymentOptions()->create([
                'type' => 'installment',
                'label' => 'Installment',
                'is_active' => true,
            ]);
        });
    }

    /**
     * Create with default resale plans.
     */
    public function withResalePlans(): static
    {
        return $this->afterCreating(function ($product) {
            // 3 Months resale
            $product->allResalePlans()->create([
                'months' => 3,
                'profit_percentage' => 20,
                'label' => '3 Months (+20%)',
                'is_active' => true,
            ]);

            // 6 Months resale
            $product->allResalePlans()->create([
                'months' => 6,
                'profit_percentage' => 40,
                'label' => '6 Months (+40%)',
                'is_active' => true,
            ]);

            // 12 Months resale
            $product->allResalePlans()->create([
                'months' => 12,
                'profit_percentage' => 80,
                'label' => '12 Months (+80%)',
                'is_active' => true,
            ]);
        });
    }

    /**
     * Create with both payment options and resale plans.
     */
    public function withAllOptions(): static
    {
        return $this->withPaymentOptions()->withResalePlans();
    }
}
