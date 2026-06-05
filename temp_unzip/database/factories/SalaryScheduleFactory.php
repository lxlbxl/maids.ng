<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalarySchedule>
 */
class SalaryScheduleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => \App\Models\MaidAssignment::factory(),
            'employer_id' => fn (array $attributes) => \App\Models\MaidAssignment::find($attributes['assignment_id'])->employer_id,
            'maid_id' => fn (array $attributes) => \App\Models\MaidAssignment::find($attributes['assignment_id'])->maid_id,
            'monthly_salary' => 50000.00,
            'salary_day' => 28,
            'employment_start_date' => now()->subMonths(2),
            'first_salary_date' => now()->subMonths(2)->day(28),
            'current_period_start' => now()->startOfMonth(),
            'current_period_end' => now()->endOfMonth(),
            'next_salary_due_date' => now()->day(28),
            'payment_status' => 'pending',
            'escrow_amount' => 50000.00,
            'escrow_funded_at' => now()->subDays(5),
            'is_active' => true,
        ];
    }
}
