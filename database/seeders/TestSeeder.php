<?php

namespace Database\Seeders;

use App\Models\Pet;
use App\Models\Test;
use App\Models\User;
use Illuminate\Database\Seeder;

class TestSeeder extends Seeder
{
    public function run(): void
    {
        $pets = Pet::all();
        $vets = User::all();

        // [pet_index, fecha, tipo (categoría), observación (incluye el examen específico y el resultado)]
        $tests = [
            [
                1,
                '2024-02-20',
                'Hematología',
                'Hemograma completo. Eritrocitos: 7.2M/µL | Hb: 14.5g/dL | Hematocrito: 44% | Leucocitos: 8.200/µL (Neutrófilos 65%, Linfocitos 28%, Monocitos 7%) | Plaquetas: 320.000/µL. Valores dentro del rango normal para la especie y edad.',
            ],
            [
                1,
                '2024-02-20',
                'Bioquímica',
                'Bioquímica sérica. Glucosa: 92mg/dL | Urea: 32mg/dL | Creatinina: 0.9mg/dL | ALT: 38U/L | AST: 29U/L | Proteínas totales: 7.1g/dL | Albumina: 3.4g/dL. Función renal y hepática normal. Sin alteraciones metabólicas.',
            ],
            [
                7,
                '2024-04-15',
                'Hematología',
                'Hemograma completo. Eritrocitos: 4.1M/µL | Hb: 9.2g/dL | Hematocrito: 28% | Leucocitos: 5.800/µL | Plaquetas: 89.000/µL. Anemia moderada normocítica normocrómica. Trombocitopenia. Compatible con Ehrlichiosis canina.',
            ],
            [
                7,
                '2024-04-15',
                'Inmunología',
                'ELISA Ehrlichia canis. Positivo (++). Título de anticuerpos elevado. Confirma diagnóstico de Ehrlichiosis. Iniciar doxiciclina 10mg/kg c/24hs por 28 días. Control de hemograma a los 14 días.',
            ],
            [
                4,
                '2024-01-23',
                'Otros',
                'Radiografía de tórax. Sin evidencia de fracturas costales. Parénquima pulmonar normal. Silueta cardíaca conservada. Solicitada tras trauma por caída. Descarta lesiones intratorácicas.',
            ],
            [
                1,
                '2024-03-01',
                'Parasitología',
                'Raspado cutáneo. Presencia de ácaros Sarcoptes scabiei en muestra analizada. Moderada cantidad. Confirma diagnóstico de sarna sarcóptica. Continuar protocolo de ivermectina. Tratar todos los animales en contacto.',
            ],
            [
                10,
                '2024-03-26',
                'Bioquímica',
                'Perfil bioquímico hepático. ALT: 189U/L (elevada) | AST: 145U/L (elevada) | Bilirrubina total: 2.8mg/dL | Proteínas totales: 5.2g/dL | Albumina: 2.1g/dL. Compatible con lipidosis hepática felina. Iniciar tratamiento de soporte hepático de inmediato.',
            ],
            [
                12,
                '2024-02-21',
                'Uroanálisis',
                'Urinálisis y sedimento. pH: 7.5 | Densidad: 1.038 | Proteínas: + | Cristales de estruvita: +++ | Leucocitos: 8-10/campo. Cristaluria severa por estruvita. Cistitis secundaria. Confirma necesidad de dieta acidificante y aumento de ingesta hídrica.',
            ],
            [
                6,
                '2024-03-19',
                'Prueba Rápida',
                'Test rápido Parvovirus. Positivo. Confirma parvovirus canino. Se mantiene hospitalización y tratamiento de soporte. Pronóstico reservado.',
            ],
        ];

        foreach ($tests as [$petIdx, $date, $type, $observation]) {
            $pet = $pets[$petIdx % $pets->count()];
            $vet = $vets->random();

            Test::create([
                'date' => $date,
                'type' => $type,
                'result' => null,
                'observation' => $observation,
                'pet_id' => $pet->id,
                'user_id' => $vet->id,
            ]);
        }
    }
}
