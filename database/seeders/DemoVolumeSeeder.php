<?php

namespace Database\Seeders;

use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\Vaccination;
use Database\Seeders\Concerns\UsesDemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Volumen opcional para probar paginación, filtros, búsqueda y el tope de exportación (5.000 filas):
 * `php artisan db:seed --class=DemoVolumeSeeder` crea `config('app.demo_volume')` mascotas (env
 * `DEMO_VOLUME`, 200 por defecto) con sus propietarios y algo de historial, usando las factories.
 * Es idempotente: sus propietarios llevan un correo `@volumen.test` y solo completa hasta llegar a
 * la cantidad configurada (cuenta las mascotas de esos propietarios). No corre en producción.
 */
class DemoVolumeSeeder extends Seeder
{
    use UsesDemoData;

    /** Dominio de los correos de los propietarios de volumen: así se reconocen y no se duplican. */
    public const EMAIL_DOMAIN = 'volumen.test';

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoVolumeSeeder no se ejecuta en producción.');

            return;
        }

        $vets = $this->demoVets();

        if ($vets->isEmpty()) {
            $this->command?->warn('Faltan los veterinarios demo: se siembra primero el demo.');
            $this->call(DemoSeeder::class);
            $vets = $this->demoVets();
        }

        $total = max(0, (int) config('app.demo_volume'));
        $existing = Pet::whereHas('owner', fn ($owners) => $owners->where('email', 'like', '%@'.self::EMAIL_DOMAIN))->count();
        $missing = max(0, $total - $existing);
        $owner = null;

        for ($i = 0; $i < $missing; $i++) {
            // Un propietario cada dos mascotas, para que haya propietarios con varias.
            if ($i % 2 === 0) {
                $owner = Owner::factory()->create(['email' => Str::lower(Str::random(12)).'@'.self::EMAIL_DOMAIN]);
            }

            $vet = $vets[$i % $vets->count()];
            $pet = Pet::factory()->for($owner)->for($vet)->create();

            Consultation::factory()->count(fake()->numberBetween(0, 3))->for($pet)->for($vet)->create();
            Vaccination::factory()->count(fake()->numberBetween(0, 2))->for($pet)->for($vet)->create();

            if (fake()->boolean(30)) {
                Surgery::factory()->for($pet)->for($vet)->create();
            }

            if (fake()->boolean(40)) {
                Test::factory()->for($pet)->for($vet)->create(['result' => null]);
            }
        }

        $this->command?->info("Mascotas de volumen: {$missing} nuevas, {$total} en total.");
    }
}
