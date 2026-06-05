<?php

namespace Database\Seeders;

use App\Models\AgentActivityLog;
use App\Models\Booking;
use App\Models\Dispute;
use App\Models\EmployerPreference;
use App\Models\MaidProfile;
use App\Models\MatchingFeePayment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        $faker = \Faker\Factory::create();

        // 1. Create more Employers
        $employers = [];
        for ($i = 0; $i < 10; $i++) {
            $user = User::create([
                'name' => $faker->name,
                'email' => $faker->unique()->safeEmail,
                'phone' => $faker->phoneNumber,
                'password' => Hash::make('password'),
                'location' => $faker->randomElement(['Lekki', 'Ikoyi', 'Ajah', 'Ikeja', 'Magodo']) . ', Lagos',
                'status' => 'active',
            ]);
            $user->assignRole('employer');
            $employers[] = $user;
        }

        // 2. Create more Maids with diverse profiles
        $maids = [];
        $roles = ['Nanny', 'Cook', 'Housekeeper', 'Elderly Care', 'Driver'];
        $skills_bank = ['cleaning', 'cooking', 'childcare', 'laundry', 'ironing', 'elderly-care', 'driving', 'first-aid', 'security'];

        for ($i = 0; $i < 20; $i++) {
            $name = $faker->name;
            $user = User::create([
                'name' => $name,
                'email' => strtolower(str_replace(' ', '.', $name)) . '@' . $faker->freeEmailDomain,
                'phone' => $faker->phoneNumber,
                'password' => Hash::make('password'),
                'location' => $faker->randomElement(['Yaba', 'Surulere', 'Ikorodu', 'Agege', 'Ojo']) . ', Lagos',
                'status' => $faker->randomElement(['active', 'suspended', 'active']),
            ]);
            $user->assignRole('maid');
            
            $maid_skills = $faker->randomElements($skills_bank, 3);
            
            MaidProfile::create([
                'user_id' => $user->id,
                'bio' => $faker->paragraph,
                'skills' => $maid_skills,
                'experience_years' => $faker->numberBetween(1, 15),
                'help_types' => $faker->randomElements(['housekeeping', 'cooking', 'nanny', 'elderly-care', 'driver'], 2),
                'schedule_preference' => $faker->randomElement(['full-time', 'part-time', 'live-in']),
                'expected_salary' => $faker->randomElement([35000, 45000, 55000, 75000, 100000]),
                'location' => $user->location,
                'nin_verified' => $faker->boolean(70),
                'background_verified' => $faker->boolean(60),
                'availability_status' => $faker->randomElement(['available', 'busy', 'available']),
                'rating' => $faker->randomFloat(1, 3.5, 5),
                'total_reviews' => $faker->numberBetween(1, 30),
            ]);
            $maids[] = $user;
        }

        // 3. Create Matching Requests (Preferences)
        $preferences = [];
        foreach ($employers as $employer) {
            $pref = EmployerPreference::create([
                'employer_id' => $employer->id,
                'help_types' => ['housekeeping', 'nanny'],
                'schedule' => 'full-time',
                'urgency' => 'immediately',
                'location' => $employer->location,
                'budget_min' => 40000,
                'budget_max' => 80000,
                'contact_name' => $employer->name,
                'contact_phone' => $employer->phone,
            ]);
            $preferences[] = $pref;
        }

        // 4. Create Financials (Matching Fees)
        foreach ($preferences as $index => $pref) {
            if ($index % 2 === 0) {
                MatchingFeePayment::create([
                    'preference_id' => $pref->id,
                    'employer_id' => $pref->employer_id,
                    'amount' => 5000,
                    'reference' => 'TEST-' . Str::upper(Str::random(10)),
                    'status' => 'paid',
                    'paid_at' => now()->subDays($index),
                ]);
            }
        }

        // 5. Create Bookings (Contracts)
        $booking_statuses = ['pending', 'active', 'completed', 'cancelled'];
        foreach ($maids as $index => $maid) {
            if ($index < 10) {
                $employer = $faker->randomElement($employers);
                $status = $faker->randomElement($booking_statuses);
                
                $booking = Booking::create([
                    'employer_id' => $employer->id,
                    'maid_id' => $maid->id,
                    'status' => $status,
                    'start_date' => now()->subDays(30),
                    'end_date' => now()->addDays(30),
                    'agreed_salary' => $maid->maidProfile->expected_salary,
                    'notes' => 'Test booking created for stability verification.',
                ]);

                // Create a dispute if cancelled
                if ($status === 'cancelled') {
                    Dispute::create([
                        'booking_id' => $booking->id,
                        'filed_by' => $employer->id,
                        'reason' => 'Maid stopped showing up after 2 days.',
                        'evidence' => 'Call logs attached.',
                        'status' => 'pending',
                    ]);
                }
            }
        }

        // 6. Create Agent Activity Logs (The Audit Trail)
        $agents = ['Sentinel', 'Treasurer', 'Referee', 'Gatekeeper'];
        $actions = [
            'Sentinel' => ['profile_audit', 'quality_check', 'trend_analysis'],
            'Treasurer' => ['payment_verif', 'payout_auth', 'escrow_audit'],
            'Referee' => ['dispute_assess', 'contract_review'],
            'Gatekeeper' => ['identity_verif', 'id_scan'],
        ];

        for ($i = 0; $i < 60; $i++) {
            $agent_name = $faker->randomElement($agents);
            $agent_action = $faker->randomElement($actions[$agent_name]);
            
            // Pick a relevant subject based on agent
            $subject = null;
            if ($agent_name === 'Gatekeeper' || $agent_name === 'Sentinel') {
                $subject = $faker->randomElement(MaidProfile::all());
            } elseif ($agent_name === 'Referee') {
                $subject = $faker->randomElement(Booking::all());
            } elseif ($agent_name === 'Treasurer') {
                $subject = $faker->randomElement(MatchingFeePayment::all());
            }

            AgentActivityLog::create([
                'agent_name' => $agent_name,
                'action' => $agent_action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->id : null,
                'decision' => $faker->randomElement(['cleared', 'flagged', 'queued_for_review', 'auto_payout']),
                'confidence_score' => $faker->numberBetween(70, 100),
                'reasoning' => "AI simulated reasoning for action {$agent_action} based on platform heuristics.",
                'requires_review' => $faker->boolean(20),
            ]);
        }
        // Seed Default AI Settings
        \App\Models\Setting::set('ai_active_provider', 'openai', 'ai');
        \App\Models\Setting::set('openai_model', 'gpt-4-turbo', 'ai');
        \App\Models\Setting::set('openrouter_model', 'anthropic/claude-3-opus', 'ai');
        \App\Models\Setting::set('platform_name', 'Maids.ng Mission Control', 'general');
        \App\Models\Setting::set('service_fee_percentage', '15', 'finance');
        \App\Models\Setting::set('openai_key', 'sk-proj-demo-placeholder', 'ai', true);
        \App\Models\Setting::set('openrouter_key', 'sk-or-v1-demo-placeholder', 'ai', true);

        // Seed Example Disputes
        $activeBooking = Booking::where('status', 'active')->first();
        if ($activeBooking) {
            \App\Models\Dispute::create([
                'booking_id' => $activeBooking->id,
                'user_id' => $activeBooking->employer_id, // Employer raised it
                'reason' => 'Delay in service arrival',
                'description' => 'The maid has not arrived for 2 hours. I need a refund or a replacement.',
                'status' => 'pending',
                'priority' => 'high'
            ]);
        }
    }
}
