<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        // Credentials MUST be changed immediately after first login in any
        // real deployment; local/dev convenience defaults only.
        User::updateOrCreate(
            ['email' => env('SUPER_ADMIN_EMAIL', 'superadmin@kuwaleesiteflow.local')],
            [
                'organization_id' => null,
                'name' => 'Kuwalee Super Admin',
                'password' => Hash::make(env('SUPER_ADMIN_PASSWORD', 'ChangeMe!12345')),
                'status' => 'active',
                'is_super_admin' => true,
                'email_verified_at' => now(),
            ]
        );
    }
}
