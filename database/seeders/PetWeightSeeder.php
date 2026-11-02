<?php

namespace Database\Seeders;

use App\Enums\PetWeightSource;
use App\Models\PetWeight;
use Database\Seeders\Concerns\UsesDemoData;
use Illuminate\Database\Seeder;

/**
 * Historial de pesos del demo, con fechas relativas a hoy: los cachorros y gatitos van subiendo hasta su peso
 * actual y los adultos varían poco. Es idempotente: reemplaza solo los pesajes sueltos de las mascotas demo y
 * mueve el pesaje de registro; los de consultas los crean las consultas.
 */
class PetWeightSeeder extends Seeder
{
    use UsesDemoData;

    /** Pesaje de registro [días atrás, proporción] y pesajes sueltos [días atrás, proporción] de cada perfil. */
    private const PROFILES = [
        'growing' => ['registration' => [75, 0.30], 'weighings' => [[60, 0.45], [45, 0.62], [30, 0.78], [14, 0.92], [3, 1.0]]],
        'adult' => ['registration' => [200, 1.04], 'weighings' => [[120, 1.03], [60, 1.02], [20, 1.01], [5, 1.0]]],
        'dieting' => ['registration' => [210, 1.12], 'weighings' => [[150, 1.10], [100, 1.07], [50, 1.04], [2, 1.0]]],
    ];

    /** Posición en PetSeeder::PETS de las mascotas que crecen (edad en meses) y de la que adelgaza (Rocky). */
    private const GROWING = [3, 6, 8, 13];

    private const DIETING = [4];

    public function run(): void
    {
        $vets = $this->demoVets();

        foreach ($this->demoPets() as $index => $pet) {
            $profile = self::PROFILES[match (true) {
                in_array($index, self::GROWING, true) => 'growing',
                in_array($index, self::DIETING, true) => 'dieting',
                default => 'adult',
            }];
            $weight = (float) $pet->weight;
            $vet = $vets[$index % max($vets->count(), 1)] ?? null;

            PetWeight::query()->where('pet_id', $pet->id)->where('source', PetWeightSource::Weighing)->delete();

            // El pesaje de registro (peso declarado) pasa a ser el primero del historial.
            [$days, $factor] = $profile['registration'];
            PetWeight::query()->where('pet_id', $pet->id)->where('source', PetWeightSource::Registration)->update([
                'weighed_at' => today()->subDays($days)->toDateString(),
                'weight' => round($weight * $factor, 1),
            ]);

            foreach ($profile['weighings'] as [$days, $factor]) {
                PetWeight::create([
                    'pet_id' => $pet->id,
                    'weight' => round($weight * $factor, 1),
                    'weighed_at' => today()->subDays($days)->toDateString(),
                    'user_id' => $vet?->id,
                    'source' => PetWeightSource::Weighing,
                ]);
            }

            $pet->refresh()->refreshCurrentWeight();
        }
    }
}
