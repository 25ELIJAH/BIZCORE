<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Creates the product only (quantity 0). To give it stock use StockService so a movement is recorded.
 *
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'sku' => strtoupper(fake()->unique()->bothify('SKU-????-####')),
            'name' => fake()->unique()->words(3, true),
            'description' => fake()->sentence(),
            'unit' => 'pcs',
            'purchase_price' => 10,
            'selling_price' => 15,
            'min_stock' => 5,
            'is_active' => true,
        ];
    }
}
