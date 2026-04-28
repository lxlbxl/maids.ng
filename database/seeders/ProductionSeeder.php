<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * Production Seeder — safe to run on a live database.
 *
 * Creates the required roles and a default admin user.
 * Seeds all system settings with sensible defaults.
 * Uses firstOrCreate throughout so it is fully idempotent.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        // ── Roles ──
        Role::firstOrCreate(['name' => 'admin']);
        Role::firstOrCreate(['name' => 'employer']);
        Role::firstOrCreate(['name' => 'maid']);

        // ── Admin User ──
        $admin = User::firstOrCreate(
            ['email' => 'admin@maids.ng'],
            [
                'name'     => 'Admin',
                'phone'    => '08000000000',
                'password' => Hash::make(env('ADMIN_DEFAULT_PASSWORD', 'ChangeMe!2026')),
                'location' => 'Lagos, Nigeria',
                'status'   => 'active',
            ]
        );
        $admin->assignRole('admin');

        // ── System Settings ──
        $this->call(SettingSeeder::class);

        $this->command->info('✅ Production seed complete — admin@maids.ng created.');
    }
}
