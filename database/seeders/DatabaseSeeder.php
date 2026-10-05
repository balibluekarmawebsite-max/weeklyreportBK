<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with the Phase 1 foundation data.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PropertySeeder::class,
            ListsSeeder::class,
            AdminUserSeeder::class,
            ReportWeekSeeder::class,
            WeekDataSeeder::class,
        ]);
    }
}
