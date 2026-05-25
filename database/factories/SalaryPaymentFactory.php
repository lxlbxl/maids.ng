<?php

namespace Database\Factories;

use App\Models\SalarySchedule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SalaryPayment>
 */
class SalaryPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $schedule = SalarySchedule::factory()->create();

        return [
            'salary_schedule_id' => $schedule->id,
            'assignment_id' => $schedule->assignment_id,
            'employer_id' => $schedule->employer_id,
            'maid_id' => $schedule->maid_id,
            'period_start_date' => now()->startOfMonth(),
            'period_end_date' => now()->endOfMonth(),
            'due_date' => now()->addDays(5),
            'gross_amount' => 50000.00,
            'deductions' => 0,
            'net_amount' => 50000.00,
            'status' => 'pending',
        ];
    }
}
