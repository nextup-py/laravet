<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $central = Department::whereRaw("LOWER(name) LIKE ?", ['%central%'])->first();

        $areGua     = City::whereRaw("LOWER(name) LIKE ?", ['%aregu%'])->where('department_id', $central?->id)->first();
        $luque      = City::whereRaw("LOWER(name) LIKE ?", ['%luque%'])->where('department_id', $central?->id)->first();
        $sanLorenzo = City::whereRaw("LOWER(name) LIKE ?", ['%san lorenzo%'])->where('department_id', $central?->id)->first();

        $nbAreguA     = Neighborhood::where('city_id', $areGua?->id)->first();
        $nbLuque      = Neighborhood::where('city_id', $luque?->id)->first();
        $nbSanLorenzo = Neighborhood::where('city_id', $sanLorenzo?->id)->first();

        $vets = [
            [
                'name'              => 'Dr. Roberto Benítez',
                'email'             => 'admin@mbopivet.com.py',
                'password'          => 'password',
                'email_verified_at' => now(),
                'department_id'     => $central?->id,
                'city_id'           => $areGua?->id,
                'neighborhood_id'   => $nbAreguA?->id,
                'address'           => 'Mcal. Estigarribia 450',
            ],
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

        foreach ($vets as $index => $vet) {
            $user = User::updateOrCreate(['email' => $vet['email']], $vet);
            $user->syncRoles($index === 0 ? ['admin'] : ['veterinarian']);
        }
    }
}
