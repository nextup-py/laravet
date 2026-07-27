<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RegionsSeeder::class,
            RolesAndPermissionsSeeder::class,
            UserSeeder::class,
            OwnerSeeder::class,
            PetSeeder::class,
            ConsultationSeeder::class,
            VaccinationSeeder::class,
            SurgerySeeder::class,
            TestSeeder::class,
        ]);
    }
}
