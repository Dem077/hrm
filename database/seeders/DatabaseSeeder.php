<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CompanyStructureSeeder::class,
            // Optional demo data (keeps existing employees; uses DEMO### staff IDs):
            // DummyCompanyStructureSeeder::class,
            // DummyEmployeesAndPunchesSeeder::class,
            // DummyPayrollStructureSeeder::class,
        ]);
    }
}
