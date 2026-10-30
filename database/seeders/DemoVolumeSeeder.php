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

/**
 * Volumen opcional para probar paginación, filtros, búsqueda y el tope de exportación (5.000 filas):
 * `php artisan db:seed --class=DemoVolumeSeeder` crea `config('app.demo_volume')` mascotas (env
 * `DEMO_VOLUME`, 200 por defecto) con sus propietarios y algo de historial, usando las factories.
 * No es idempotente a propósito (cada corrida suma datos) y no corre en producción.
 */
class DemoVolumeSeeder extends Seeder
{
    use UsesDemoData;

    public function run(): void
    {
        if (app()->environment('production')) {
            $this->command?->error('DemoVolumeSeeder no se ejecuta en producción.');

            return;
        }

        $vets = $this->demoVets();

        if ($vets->isEmpty()) {
            $this->command?->error('No hay veterinarios demo: ejecuta antes DemoSeeder.');

            return;
        }

        $total = max(0, (int) config('app.demo_volume'));
        $owner = null;

        for ($i = 0; $i < $total; $i++) {
            // Un propietario cada dos mascotas, para que haya propietarios con varias.
            if ($i % 2 === 0) {
                $owner = Owner::factory()->create();
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

        $this->command?->info("Se crearon {$total} mascotas demo con su historial.");
    }
}
