<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $central = Department::whereRaw("LOWER(name) LIKE ?", ['%central%'])->first();

        $luque      = City::whereRaw("LOWER(name) LIKE ?", ['%luque%'])->where('department_id', $central?->id)->first();
        $sanLorenzo = City::whereRaw("LOWER(name) LIKE ?", ['%san lorenzo%'])->where('department_id', $central?->id)->first();

        $nbLuque      = Neighborhood::where('city_id', $luque?->id)->first();
        $nbSanLorenzo = Neighborhood::where('city_id', $sanLorenzo?->id)->first();

        $vets = [
            [
                'name'              => 'Dra. María González',
                'email'             => 'maria.gonzalez@mbopivet.com.py',
                'password'          => 'password',
                'email_verified_at' => now(),
                'department_id'     => $central?->id,
                'city_id'           => $luque?->id,
                'neighborhood_id'   => $nbLuque?->id,
                'address'           => 'Av. República 215',
            ],
            [
                'name'              => 'Dr. Carlos Giménez',
                'email'             => 'carlos.gimenez@mbopivet.com.py',
                'password'          => 'password',
                'email_verified_at' => now(),
                'department_id'     => $central?->id,
                'city_id'           => $sanLorenzo?->id,
                'neighborhood_id'   => $nbSanLorenzo?->id,
                'address'           => 'Av. Defensores del Chaco 780',
            ],
        ];

        foreach ($vets as $vet) {
            $user = User::updateOrCreate(['email' => $vet['email']], $vet);
            $user->syncRoles(['veterinarian']);
        }

        $this->call([
            OwnerSeeder::class,
            PetSeeder::class,
            ConsultationSeeder::class,
            VaccinationSeeder::class,
            SurgerySeeder::class,
            TestSeeder::class,
        ]);
    }
}
