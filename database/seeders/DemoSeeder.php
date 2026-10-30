<?php

namespace Database\Seeders;

use App\Filament\Concerns\ClinicRoles;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Spatie\Permission\PermissionRegistrar;

class DemoSeeder extends Seeder
{
    /**
     * Personal demo: contraseña `password` y correos fijos, por eso nunca corre en producción.
     * Es idempotente: se puede volver a ejecutar y actualiza (no duplica) lo ya sembrado.
     */
    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoSeeder no se ejecuta en producción.');

            return;
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $central = Department::where('name', 'Central')->first();

        $luque = City::where('name', 'Luque')->where('department_id', $central?->id)->first();
        $sanLorenzo = City::where('name', 'San Lorenzo')->where('department_id', $central?->id)->first();

        $nbLuque = Neighborhood::where('city_id', $luque?->id)->orderBy('id')->first();
        $nbSanLorenzo = Neighborhood::where('city_id', $sanLorenzo?->id)->orderBy('id')->first();

        $staff = [
            [
                'name' => 'Dra. María González',
                'email' => 'maria.gonzalez@mbopivet.com.py',
                'password' => 'password',
                'email_verified_at' => now(),
                'department_id' => $central?->id,
                'city_id' => $luque?->id,
                'neighborhood_id' => $nbLuque?->id,
                'address' => 'Av. República 215',
                'role' => ClinicRoles::VETERINARIAN,
            ],
            [
                'name' => 'Dr. Carlos Giménez',
                'email' => 'carlos.gimenez@mbopivet.com.py',
                'password' => 'password',
                'email_verified_at' => now(),
                'department_id' => $central?->id,
                'city_id' => $sanLorenzo?->id,
                'neighborhood_id' => $nbSanLorenzo?->id,
                'address' => 'Av. Defensores del Chaco 780',
                'role' => ClinicRoles::VETERINARIAN,
            ],
            [
                'name' => 'Laura Benítez',
                'email' => 'laura.benitez@mbopivet.com.py',
                'password' => 'password',
                'email_verified_at' => now(),
                'department_id' => $central?->id,
                'city_id' => $luque?->id,
                'neighborhood_id' => $nbLuque?->id,
                'address' => 'Calle Palma 340',
                'role' => ClinicRoles::ASSISTANT,
            ],
        ];

        foreach ($staff as $member) {
            $role = Arr::pull($member, 'role');

            $user = User::updateOrCreate(['email' => $member['email']], $member);
            $user->syncRoles([$role]);
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
