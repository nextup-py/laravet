<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use Illuminate\Database\Seeder;

class VaccinationSeeder extends Seeder
{
    public function run(): void
    {
        $pets = Pet::all();
        $vets = User::all();

        // [pet_index, vacuna, fecha_aplicación, próxima_aplicación, lote, fabricante, observación]
        $vaccinations = [
            [0,  'Séptuple',           '2024-01-15', '2025-01-15', 'L2401PY',  'Zoetis',              'Primera dosis del año. Paciente en buen estado general.'],
            [0,  'Antirrábica',        '2024-01-15', '2025-01-15', 'R2401PY',  'Boehringer Ingelheim', 'Aplicada junto con Séptuple. Sin reacciones adversas.'],
            [1,  'Séptuple',           '2024-02-20', '2025-02-20', 'L2402PY',  'Zoetis',              'Refuerzo anual. Paciente cooperadora.'],
            [1,  'Antirrábica',        '2024-02-20', '2025-02-20', 'R2402PY',  'MSD Animal Health',   null],
            [2,  'Séptuple',           '2023-11-10', '2024-11-10', 'L2311PY',  'Zoetis',              'Refuerzo. Propietario informado sobre próximo vencimiento.'],
            [2,  'Antirrábica',        '2023-11-10', '2024-11-10', 'R2311PY',  'Boehringer Ingelheim', null],
            [3,  'Séptuple',           '2024-03-05', '2024-06-05', 'L2403PY',  'Zoetis',              'Primera dosis (cachorra). Requiere 2da dosis en 21 días y refuerzo a los 3 meses.'],
            [4,  'Séptuple',           '2024-01-22', '2025-01-22', 'L2401PY2', 'MSD Animal Health',   null],
            [4,  'Antirrábica',        '2024-01-22', '2025-01-22', 'R2401PY2', 'Zoetis',              null],
            [5,  'Séptuple',           '2024-04-10', '2025-04-10', 'L2404PY',  'Boehringer Ingelheim', 'Refuerzo post-recuperación de piómetra.'],
            [6,  'Séptuple',           '2024-03-18', '2024-06-18', 'L2403PY2', 'Zoetis',              'Primera vacunación (rescatado de la calle). Segundo refuerzo pendiente.'],
            [7,  'Séptuple',           '2023-12-01', '2024-12-01', 'L2312PY',  'MSD Animal Health',   null],
            [7,  'Antirrábica',        '2023-12-01', '2024-12-01', 'R2312PY',  'Zoetis',              null],
            [8,  'Séptuple',           '2024-02-14', '2024-05-14', 'L2402PY2', 'Zoetis',              'Primera dosis. Cachorro de 4 meses.'],
            [9,  'Séptuple',           '2024-01-08', '2025-01-08', 'L2401PY3', 'Boehringer Ingelheim', null],
            [9,  'Antirrábica',        '2024-01-08', '2025-01-08', 'R2401PY3', 'MSD Animal Health',   null],
            [10, 'Triple Felina',      '2024-03-25', '2025-03-25', 'TF2403PY', 'Zoetis',              'Primera vacunación del paciente. Sin historial previo.'],
            [11, 'Triple Felina',      '2024-02-10', '2025-02-10', 'TF2402PY', 'Boehringer Ingelheim', 'Refuerzo anual. Paciente tranquila.'],
            [11, 'Antirrábica',        '2024-02-10', '2025-02-10', 'R2402PY2', 'Zoetis',              null],
            [12, 'Triple Felina',      '2023-10-15', '2024-10-15', 'TF2310PY', 'MSD Animal Health',   'Refuerzo. Se recuerda al propietario dieta húmeda obligatoria.'],
            [13, 'Triple Felina',      '2024-04-01', '2024-07-01', 'TF2404PY', 'Zoetis',              'Primera dosis (gatita de 3 meses). Segunda dosis pendiente.'],
            [14, 'Triple Felina',      '2024-01-30', '2025-01-30', 'TF2401PY', 'Boehringer Ingelheim', null],
            [14, 'Leucemia Felina',    '2024-01-30', '2025-01-30', 'LF2401PY', 'MSD Animal Health',   'Se vacuna contra Leucemia por vivir con otros gatos.'],
        ];

        foreach ($vaccinations as [$petIdx, $vaccine, $appDate, $nextApp, $batch, $manufacturer, $observation]) {
            $pet = $pets[$petIdx % $pets->count()];
            $vet = $vets->random();

            Vaccination::create([
                'vaccine' => $vaccine,
                'application_date' => $appDate,
                'next_application' => $nextApp,
                'batch' => $batch,
                'manufacturer' => $manufacturer,
                'observation' => $observation,
                'pet_id' => $pet->id,
                'user_id' => $vet->id,
            ]);
        }
    }
}
