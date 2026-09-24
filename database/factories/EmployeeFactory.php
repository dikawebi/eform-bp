<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_number' => 'NIK-'.$this->faker->unique()->numerify('######'),
            'name' => $this->faker->name(),
            'department' => $this->faker->randomElement(['HRGA', 'Finance', 'Operasional', 'Workshop', 'HSE']),
            'level' => $this->faker->randomElement(['Staff', 'Supervisor', 'Superintendent', 'Manager']),
            'job_title' => $this->faker->jobTitle(),
            'roster' => $this->faker->randomElement(['4:2', '6:2', '8:2', null]),
            'employment_status' => 'permanent',
            'poh_status' => $this->faker->randomElement(['local', 'non_local']),
            'poh_city' => $this->faker->city(),
            'poh_province' => $this->faker->state(),
            'is_project_based' => false,
            'active' => true,
            'joined_at' => $this->faker->date(),
        ];
    }
}
