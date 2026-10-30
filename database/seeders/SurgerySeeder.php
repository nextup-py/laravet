<?php

namespace Database\Seeders;

use App\Models\Surgery;
use Database\Seeders\Concerns\UsesDemoData;
use Illuminate\Database\Seeder;

class SurgerySeeder extends Seeder
{
    use UsesDemoData;

    public function run(): void
    {
        $pets = $this->demoPets();
        $vets = $this->demoVets();

        // Fechas relativas a hoy (negativo = pasado, 0 = hoy, positivo = programada), así siempre hay
        // cirugías realizadas, de hoy y programadas. Las que acompañan una consulta reciente usan sus mismos días.
        // [pet_index, días_desde_hoy, tipo, observación]
        $surgeries = [
            [
                2,
                -1330,
                'Castración',
                'Orquiectomía bilateral bajo anestesia general (propofol + isoflurano). Sin complicaciones intraoperatorias. Alta a las 4 horas del procedimiento.',
            ],
            [
                5,
                -30,
                'Ovariohisterectomía',
                'Cirugía de urgencia por piómetra abierta. Útero con abundante contenido purulento. Ligadura de pedículos ováricos y cuerpo uterino sin complicaciones. Recuperación anestésica favorable.',
            ],
            [
                4,
                -3,
                'Sutura de herida',
                'Herida lacerante de 4cm en región tibial derecha. Limpieza quirúrgica y sutura con nylon 2-0, 5 puntos simples. Control en 7 días para retirar los puntos.',
            ],
            [
                7,
                -870,
                'Extracción dental',
                'Extracción de piezas dentales 108 y 208 con grado III de enfermedad periodontal bajo anestesia general. Lavado con clorhexidina. Antibioterapia postoperatoria por 7 días.',
            ],
            [
                12,
                -15,
                'Desobstrucción uretral',
                'Obstrucción uretral completa por tapón de estruvita. Desobstrucción bajo anestesia (propofol IV) con sonda uretral 3.5Fr. Sonda retirada a las 48hs. Alta con dieta húmeda exclusiva.',
            ],
            [
                9,
                -1500,
                'Castración',
                'Orquiectomía bilateral de rutina. Procedimiento electivo sin complicaciones. Propietario informado sobre comportamiento post-castración.',
            ],
            [
                8,
                0,
                'Castración',
                'Orquiectomía bilateral electiva. Ayuno de 12 horas previo. Cirugía del día.',
            ],
            [
                6,
                3,
                'Castración',
                'Castración electiva programada. Indicar ayuno de 12 horas y estudios prequirúrgicos al ingreso.',
            ],
        ];

        foreach ($surgeries as $i => [$petIdx, $daysFromToday, $type, $observation]) {
            $pet = $pets[$petIdx % $pets->count()];

            Surgery::updateOrCreate(['pet_id' => $pet->id, 'type' => $type], [
                'date' => now()->addDays($daysFromToday)->toDateString(),
                'observation' => $observation,
                'user_id' => $vets[$i % $vets->count()]->id,
            ]);
        }
    }
}
