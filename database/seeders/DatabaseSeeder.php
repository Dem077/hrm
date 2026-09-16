<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * Fresh demo stack (login admin@demo.local / password):
     *   php artisan migrate:fresh --seed
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CompanyStructureSeeder::class,
            DummyEmployeesAndPunchesSeeder::class,
            DummyCompanyStructureSeeder::class,
            DummyPayrollStructureSeeder::class,
            DemoAppSeeder::class,
        ]);
    }
}
