<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\Surgery;
use App\Models\User;
use Illuminate\Database\Seeder;

class SurgerySeeder extends Seeder
{
    public function run(): void
    {
        $pets = Pet::all();
        $vets = User::all();

        // [pet_index, fecha, tipo, observación]
        $surgeries = [
            [
                2,
                '2022-03-10',
                'Castración',
                'Orquiectomía bilateral bajo anestesia general (propofol + isoflurano). Sin complicaciones intraoperatorias. Alta a las 4 horas del procedimiento.',
            ],
            [
                5,
                '2024-03-25',
                'Ovariohisterectomía',
                'Cirugía de urgencia por piómetra abierta. Útero con abundante contenido purulento. Ligadura de pedículos ováricos y cuerpo uterino sin complicaciones. Recuperación anestésica favorable.',
            ],
            [
                4,
                '2024-04-08',
                'Sutura de herida',
                'Herida lacerante de 4cm en región tibial derecha. Limpieza quirúrgica y sutura con nylon 2-0, 5 puntos simples. Puntos retirados a los 10 días sin signos de infección.',
            ],
            [
                7,
                '2023-06-14',
                'Extracción dental',
                'Extracción de piezas dentales 108 y 208 con grado III de enfermedad periodontal bajo anestesia general. Lavado con clorhexidina. Antibioterapia postoperatoria por 7 días.',
            ],
            [
                12,
                '2024-02-20',
                'Desobstrucción uretral',
                'Obstrucción uretral completa por tapón de estruvita. Desobstrucción bajo anestesia (propofol IV) con sonda uretral 3.5Fr. Sonda retirada a las 48hs. Alta con dieta húmeda exclusiva.',
            ],
            [
                9,
                '2022-09-05',
                'Castración',
                'Orquiectomía bilateral de rutina. Procedimiento electivo sin complicaciones. Propietario informado sobre comportamiento post-castración.',
            ],
        ];

        foreach ($surgeries as [$petIdx, $date, $type, $observation]) {
            $pet = $pets[$petIdx % $pets->count()];
            $vet = $vets->random();

            Surgery::create([
                'date' => $date,
                'type' => $type,
                'observation' => $observation,
                'pet_id' => $pet->id,
                'user_id' => $vet->id,
            ]);
        }
    }
}
