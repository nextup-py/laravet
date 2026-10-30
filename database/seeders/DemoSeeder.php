<?php

namespace Database\Seeders;

use App\Filament\Concerns\ClinicRoles;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class DemoSeeder extends Seeder
{
    /**
     * Personal demo con correos fijos, por eso nunca corre en producción. La contraseña sale de
     * `DEMO_PASSWORD` (se aplica siempre); si no está definida, `password` en local/testing y una
     * aleatoria que se muestra una sola vez en staging (solo al crear el usuario, no se cambia después).
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
                'email_verified_at' => now(),
                'department_id' => $central?->id,
                'city_id' => $luque?->id,
                'neighborhood_id' => $nbLuque?->id,
                'address' => 'Calle Palma 340',
                'role' => ClinicRoles::ASSISTANT,
            ],
        ];

        $fixedPassword = config('app.demo_password');

        foreach ($staff as $member) {
            $role = Arr::pull($member, 'role');

            $user = User::firstOrNew(['email' => $member['email']]);
            $user->fill($member);

            if ($fixedPassword) {
                $user->password = $fixedPassword;
            } elseif (! $user->exists) {
                $user->password = $generated = $this->defaultPassword();

                if ($generated !== 'password') {
                    $this->command?->warn("Usuario demo {$member['email']} creado. Password generada: {$generated} — guárdala, no se vuelve a mostrar.");
                }
            }

            $user->save();
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

    private function defaultPassword(): string
    {
        return app()->environment(['local', 'testing']) ? 'password' : Str::password(16);
    }
}
