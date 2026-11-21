<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Employee> */
class EmployeeFactory extends Factory
{
    public function definition(): array
    {
        static $n = 0;
        $n++;

        return [
            'employee_number' => sprintf('EMP-T%05d', $n),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'email' => fake()->unique()->safeEmail(),
            'job_title' => 'Clerk',
            'hire_date' => '2024-01-01',
            'status' => 'active',
            'annual_leave_days' => 21,
        ];
    }
}
