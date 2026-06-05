<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\EmployerPreference>
 */
class EmployerPreferenceFactory extends Factory
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
            'help_types' => ['nanny', 'cook'],
            'schedule' => 'full_time',
            'urgency' => 'normal',
            'location' => $this->faker->address,
            'city' => 'Lagos',
            'state' => 'Lagos',
            'budget_min' => 50000,
            'budget_max' => 80000,
            'contact_name' => $this->faker->name,
            'contact_phone' => $this->faker->phoneNumber,
            'contact_email' => $this->faker->email,
            'matching_status' => 'pending',
        ];
    }
}
