<?php

namespace Database\Seeders;

use App\Enums\RoleType;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::updateOrCreate(
            ['email' => 'ota@bluekarmasecrets.com'],
            [
                'name' => 'BKDS Admin',
                'job_title' => 'Director of Sales / Revenue',
                'is_active' => true,
                // IMPORTANT: change this password after first login (Settings > Users).
                'password' => Hash::make('password'),
            ],
        );

        $adminRole = Role::where('slug', RoleType::Admin->value)->first();
        if ($adminRole) {
            $admin->roles()->syncWithoutDetaching([$adminRole->id]);
        }
    }
}
