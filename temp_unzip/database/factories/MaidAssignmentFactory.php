<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MaidAssignment>
 */
class MaidAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employer_id' => \App\Models\User::factory(),
            'maid_id' => \App\Models\User::factory(),
            'preference_id' => \App\Models\EmployerPreference::factory(),
            'assigned_by' => 'admin',
            'assignment_type' => 'manual',
            'status' => 'accepted',
            'salary_amount' => 50000.00,
            'salary_currency' => 'NGN',
            'job_location' => $this->faker->address,
            'job_type' => 'full_time',
            'started_at' => now()->subMonth(),
            'matching_fee_paid' => true,
            'matching_fee_amount' => 5000.00,
            'guarantee_match' => false,
            'ai_match_score' => 0.85,
        ];
    }
}
