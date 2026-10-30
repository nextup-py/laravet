<?php

namespace Database\Seeders;

use App\Models\Vaccination;
use Database\Seeders\Concerns\UsesDemoData;
use Illuminate\Database\Seeder;

class VaccinationSeeder extends Seeder
{
    use UsesDemoData;

    public function run(): void
    {
        $pets = $this->demoPets();
        $vets = $this->demoVets();

        // Fechas relativas a hoy, así el demo siempre muestra los cuatro estados:
        // vencida (próxima < 0), por vencer (0 a 7 días), al día (> 7) y renovada (hay una aplicación más reciente).
        // [pet_index, vacuna, días_desde_aplicación, días_hasta_próxima, lote, fabricante, observación]
        $vaccinations = [
            [0,  'Séptuple',        120, 245, 'L2401PY',   'Zoetis',               'Primera dosis del año. Paciente en buen estado general.'],
            [0,  'Antirrábica',     120, 245, 'R2401PY',   'Boehringer Ingelheim', 'Aplicada junto con Séptuple. Sin reacciones adversas.'],
            [1,  'Séptuple',        350,  15, 'L2402PY',   'Zoetis',               'Refuerzo anual. Paciente cooperadora.'],
            [1,  'Antirrábica',     358,   5, 'R2402PY',   'MSD Animal Health',    null],
            [2,  'Séptuple',        400, -35, 'L2311PY',   'Zoetis',               'Refuerzo. Propietario informado sobre próximo vencimiento.'],
            [2,  'Antirrábica',     400, -35, 'R2311PY',   'Boehringer Ingelheim', null],
            [3,  'Séptuple',         63, -21, 'L2403PY',   'Zoetis',               'Primera dosis (cachorra). Requiere 2da dosis en 21 días y refuerzo a los 3 meses.'],
            [3,  'Séptuple',         21,  70, 'L2403PYB',  'Zoetis',               'Segunda dosis (cachorra). Refuerzo anual programado.'],
            [4,  'Séptuple',        300,  65, 'L2401PY2',  'MSD Animal Health',    null],
            [4,  'Antirrábica',     300,  65, 'R2401PY2',  'Zoetis',               null],
            [5,  'Séptuple',        200, 165, 'L2404PY',   'Boehringer Ingelheim', 'Refuerzo post-recuperación de piómetra.'],
            [6,  'Séptuple',         18,   3, 'L2403PY2',  'Zoetis',               'Primera vacunación (rescatado de la calle). Segundo refuerzo próximo.'],
            [7,  'Séptuple',        330,  35, 'L2312PY',   'MSD Animal Health',    null],
            [7,  'Antirrábica',     330,  35, 'R2312PY',   'Zoetis',               null],
            [8,  'Séptuple',         28,   0, 'L2402PY2',  'Zoetis',               'Primera dosis. Cachorro de 4 meses. Refuerzo pendiente.'],
            [9,  'Séptuple',        380, -15, 'L2401PY3',  'Boehringer Ingelheim', null],
            [9,  'Antirrábica',     380, -15, 'R2401PY3',  'MSD Animal Health',    null],
            [10, 'Triple Felina',   220, 145, 'TF2403PY',  'Zoetis',               'Primera vacunación del paciente. Sin historial previo.'],
            [11, 'Triple Felina',   340,  25, 'TF2402PY',  'Boehringer Ingelheim', 'Refuerzo anual. Paciente tranquila.'],
            [11, 'Antirrábica',     340,  25, 'R2402PY2',  'Zoetis',               null],
            [12, 'Triple Felina',   395, -30, 'TF2310PY',  'MSD Animal Health',    'Refuerzo. Se recuerda al propietario dieta húmeda obligatoria.'],
            [13, 'Triple Felina',    15,   6, 'TF2404PY',  'Zoetis',               'Primera dosis (gatita de 3 meses). Segunda dosis próxima.'],
            [14, 'Triple Felina',   290,  75, 'TF2401PY',  'Boehringer Ingelheim', null],
            [14, 'Leucemia Felina', 290,  75, 'LF2401PY',  'MSD Animal Health',    'Se vacuna contra Leucemia por vivir con otros gatos.'],
        ];

        foreach ($vaccinations as $i => [$petIdx, $vaccine, $appDaysAgo, $nextInDays, $batch, $manufacturer, $observation]) {
            $pet = $pets[$petIdx % $pets->count()];

            Vaccination::updateOrCreate(['pet_id' => $pet->id, 'vaccine' => $vaccine, 'batch' => $batch], [
                'application_date' => now()->subDays($appDaysAgo)->toDateString(),
                'next_application' => now()->addDays($nextInDays)->toDateString(),
                'manufacturer' => $manufacturer,
                'observation' => $observation,
                'user_id' => $vets[$i % $vets->count()]->id,
            ]);
        }
    }
}
