<?php

namespace Database\Seeders;

use App\Filament\Concerns\ClinicRoles;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use App\Models\User;
use App\Settings\ClinicSettings;
use App\Support\SeedReport;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DemoSeeder extends Seeder
{
    /** Correos del personal demo (los usa el borrado seguro del demo). */
    public const STAFF_EMAILS = [
        'maria.gonzalez@mbopivet.com.py',
        'carlos.gimenez@mbopivet.com.py',
        'laura.benitez@mbopivet.com.py',
    ];

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

        if (! Role::where('name', ClinicRoles::VETERINARIAN)->exists() || ! Department::exists()) {
            $this->command?->warn('Faltan los roles o las regiones: se siembra primero la base del sistema.');
            $this->call(ProductionSeeder::class);
        }

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

            $plain = null;

            if ($fixedPassword) {
                $plain = $fixedPassword;
            } elseif (! $user->exists) {
                $plain = $this->defaultPassword();

                if ($plain !== 'password') {
                    $this->command?->warn("Usuario demo {$member['email']} creado. Password generada: {$plain} — guárdala, no se vuelve a mostrar.");
                }
            }

            if ($plain !== null) {
                $user->password = $plain;
            }

            $user->save();
            $user->syncRoles([$role]);

            app(SeedReport::class)->user($user->email, $role, $plain);
        }

        $this->fillClinicSettings();

        $this->call([
            OwnerSeeder::class,
            PetSeeder::class,
            ConsultationSeeder::class,
            VaccinationSeeder::class,
            SurgerySeeder::class,
            TestSeeder::class,
        ]);

        $this->command?->info('Datos demo listos: personal, propietarios, mascotas e historial con fechas relativas a hoy. Puedes volver a ejecutarlo para refrescar las fechas.');
    }

    /**
     * Completa los datos de la clínica que estén vacíos (nunca pisa lo que ya cargaron), para que PDFs
     * y avisos por WhatsApp del demo salgan con contacto, RUC y horario. Los valores son de ejemplo.
     * No se carga el correo: sin él no se envían recordatorios, y los correos de los propietarios demo
     * parecen reales (gmail, hotmail), así que no deben recibir nada desde un entorno de prueba.
     */
    private function fillClinicSettings(): void
    {
        $settings = ClinicSettings::tryGet();

        if ($settings === null) {
            return;
        }

        $demo = [
            'address' => 'Calle Mariscal Estigarribia 123, Areguá',
            'phone' => '(0291) 432 000',
            'whatsapp' => '0981 000 000',
            'business_hours' => 'Lunes a viernes de 8:00 a 18:00 · Sábados de 8:00 a 12:00',
            'ruc' => '80099999-0',
            'razon_social' => 'Clínica Veterinaria Mbopivet S.R.L.',
        ];

        foreach ($demo as $property => $value) {
            if (blank($settings->{$property})) {
                $settings->{$property} = $value;
            }
        }

        $settings->save();
    }

    private function defaultPassword(): string
    {
        return app()->environment(['local', 'testing']) ? 'password' : Str::password(16);
    }
}
