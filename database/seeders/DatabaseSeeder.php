<?php

namespace Database\Seeders;

use App\Models\MaidProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $employerRole = Role::firstOrCreate(['name' => 'employer']);
        $maidRole = Role::firstOrCreate(['name' => 'maid']);

        // ── Admin User ──
        $admin = User::firstOrCreate(
            ['email' => 'admin@maids.ng'],
            [
                'name' => 'Admin User',
                'phone' => '08012345678',
                'password' => Hash::make('password'),
                'location' => 'Lagos, Nigeria',
                'status' => 'active',
            ]
        );
        $admin->assignRole($adminRole);

        // ── Employer User ──
        $employer = User::firstOrCreate(
            ['email' => 'employer@maids.ng'],
            [
                'name' => 'Adaeze Okonkwo',
                'phone' => '08098765432',
                'password' => Hash::make('password'),
                'location' => 'Lekki, Lagos',
                'status' => 'active',
            ]
        );
        $employer->assignRole($employerRole);

        // ── Maid Users ──
        $maids = [
            [
                'name' => 'Blessing Okafor',
                'email' => 'maid@maids.ng',
                'phone' => '08055551111',
                'location' => 'Ajah, Lagos',
                'profile' => [
                    'bio' => 'Experienced live-in housekeeper with 5 years of service in Lagos homes. Excellent at deep cleaning, laundry, and organizing.',
                    'skills' => ['cleaning', 'laundry', 'cooking', 'child-minding', 'organizing'],
                    'experience_years' => 5,
                    'help_types' => ['housekeeping', 'cooking', 'live-in'],
                    'schedule_preference' => 'full-time',
                    'expected_salary' => 45000,
                    'location' => 'Ajah, Lagos',
                    'state' => 'Lagos',
                    'lga' => 'Eti-Osa',
                    'nin_verified' => true,
                    'background_verified' => true,
                    'availability_status' => 'available',
                    'rating' => 4.8,
                    'total_reviews' => 12,
                ],
            ],
            [
                'name' => 'Grace Adeyemi',
                'email' => 'grace@maids.ng',
                'phone' => '08055552222',
                'location' => 'Ikeja, Lagos',
                'profile' => [
                    'bio' => 'Professional nanny with childcare certification. Loves children and experienced with newborns to 10-year-olds.',
                    'skills' => ['childcare', 'cooking', 'first-aid', 'tutoring', 'cleaning'],
                    'experience_years' => 7,
                    'help_types' => ['nanny', 'cooking'],
                    'schedule_preference' => 'full-time',
                    'expected_salary' => 55000,
                    'location' => 'Ikeja, Lagos',
                    'state' => 'Lagos',
                    'lga' => 'Ikeja',
                    'nin_verified' => true,
                    'background_verified' => true,
                    'availability_status' => 'available',
                    'rating' => 4.9,
                    'total_reviews' => 18,
                ],
            ],
            [
                'name' => 'Fatima Ibrahim',
                'email' => 'fatima@maids.ng',
                'phone' => '08055553333',
                'location' => 'Victoria Island, Lagos',
                'profile' => [
                    'bio' => 'Skilled cook specializing in Nigerian, Continental, and Asian cuisine. Also helps with general housekeeping.',
                    'skills' => ['cooking', 'baking', 'meal-planning', 'cleaning', 'shopping'],
                    'experience_years' => 4,
                    'help_types' => ['cooking', 'housekeeping'],
                    'schedule_preference' => 'full-time',
                    'expected_salary' => 50000,
                    'location' => 'Victoria Island, Lagos',
                    'state' => 'Lagos',
                    'lga' => 'Eti-Osa',
                    'nin_verified' => true,
                    'background_verified' => false,
                    'availability_status' => 'available',
                    'rating' => 4.6,
                    'total_reviews' => 8,
                ],
            ],
            [
                'name' => 'Chioma Eze',
                'email' => 'chioma@maids.ng',
                'phone' => '08055554444',
                'location' => 'Surulere, Lagos',
                'profile' => [
                    'bio' => 'Caring elderly companion with nursing assistant background. Patient, gentle, and experienced with medications.',
                    'skills' => ['elderly-care', 'medication-management', 'cooking', 'companionship', 'light-cleaning'],
                    'experience_years' => 6,
                    'help_types' => ['elderly-care', 'housekeeping'],
                    'schedule_preference' => 'full-time',
                    'expected_salary' => 60000,
                    'location' => 'Surulere, Lagos',
                    'state' => 'Lagos',
                    'lga' => 'Surulere',
                    'nin_verified' => true,
                    'background_verified' => true,
                    'availability_status' => 'available',
                    'rating' => 4.7,
                    'total_reviews' => 15,
                ],
            ],
            [
                'name' => 'Amina Hassan',
                'email' => 'amina@maids.ng',
                'phone' => '08055555555',
                'location' => 'Yaba, Lagos',
                'profile' => [
                    'bio' => 'Reliable part-time helper available on weekends and evenings. Great for quick cleanups and laundry.',
                    'skills' => ['cleaning', 'laundry', 'ironing', 'organizing'],
                    'experience_years' => 3,
                    'help_types' => ['housekeeping'],
                    'schedule_preference' => 'part-time',
                    'expected_salary' => 25000,
                    'location' => 'Yaba, Lagos',
                    'state' => 'Lagos',
                    'lga' => 'Yaba',
                    'nin_verified' => true,
                    'background_verified' => true,
                    'availability_status' => 'available',
                    'rating' => 4.5,
                    'total_reviews' => 6,
                ],
            ],
            [
                'name' => 'Joy Nwosu',
                'email' => 'joy@maids.ng',
                'phone' => '08055556666',
                'location' => 'Lekki, Lagos',
                'profile' => [
                    'bio' => 'Professional live-in housekeeper. Discreet, thorough and great at managing large households.',
                    'skills' => ['deep-cleaning', 'laundry', 'cooking', 'gardening', 'pet-care'],
                    'experience_years' => 8,
                    'help_types' => ['housekeeping', 'cooking', 'live-in'],
                    'schedule_preference' => 'full-time',
                    'expected_salary' => 65000,
                    'location' => 'Lekki, Lagos',
                    'state' => 'Lagos',
                    'lga' => 'Eti-Osa',
                    'nin_verified' => true,
                    'background_verified' => true,
                    'availability_status' => 'available',
                    'rating' => 4.95,
                    'total_reviews' => 22,
                ],
            ],
        ];

        foreach ($maids as $m) {
            $user = User::firstOrCreate(
                ['email' => $m['email']],
                [
                    'name' => $m['name'],
                    'phone' => $m['phone'],
                    'password' => Hash::make('password'),
                    'location' => $m['location'],
                    'status' => 'active',
                ]
            );
            $user->assignRole($maidRole);

            MaidProfile::firstOrCreate(
                ['user_id' => $user->id],
                $m['profile']
            );
        }

        $this->call([
            SettingSeeder::class,
            AgentKnowledgeSeeder::class,
            TestDataSeeder::class,
        ]);
    }
}
