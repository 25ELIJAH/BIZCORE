<?php

namespace Database\Factories;

use App\Models\Company;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'registration_number' => fake()->unique()->bothify('REG-#######'),
            'address' => fake()->address(),
            'email' => fake()->companyEmail(),
            'phone' => '+254 7'.fake()->numerify('## ### ###'),
            'currency' => 'KES',
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
