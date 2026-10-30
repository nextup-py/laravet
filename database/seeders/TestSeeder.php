<?php

namespace Database\Seeders;

use App\Models\Test;
use Database\Seeders\Concerns\UsesDemoData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class TestSeeder extends Seeder
{
    use UsesDemoData;

    public function run(): void
    {
        $pets = $this->demoPets();
        $vets = $this->demoVets();

        // Fechas relativas a hoy, alineadas con las consultas recientes. Los de `true` tienen un PDF de
        // resultado de ejemplo (estado «Con resultado»); el resto queda «Pendiente».
        // [pet_index, días_atrás, tipo (categoría), observación (incluye el examen específico y el resultado), con_resultado]
        $tests = [
            [
                1,
                20,
                'Hematología',
                'Hemograma completo. Eritrocitos: 7.2M/µL | Hb: 14.5g/dL | Hematocrito: 44% | Leucocitos: 8.200/µL (Neutrófilos 65%, Linfocitos 28%, Monocitos 7%) | Plaquetas: 320.000/µL. Valores dentro del rango normal para la especie y edad.',
                true,
            ],
            [
                1,
                20,
                'Bioquímica',
                'Bioquímica sérica. Glucosa: 92mg/dL | Urea: 32mg/dL | Creatinina: 0.9mg/dL | ALT: 38U/L | AST: 29U/L | Proteínas totales: 7.1g/dL | Albumina: 3.4g/dL. Función renal y hepática normal. Sin alteraciones metabólicas.',
                true,
            ],
            [
                7,
                2,
                'Hematología',
                'Hemograma completo. Eritrocitos: 4.1M/µL | Hb: 9.2g/dL | Hematocrito: 28% | Leucocitos: 5.800/µL | Plaquetas: 89.000/µL. Anemia moderada normocítica normocrómica. Trombocitopenia. Compatible con Ehrlichiosis canina.',
                false,
            ],
            [
                7,
                1,
                'Inmunología',
                'ELISA Ehrlichia canis. Positivo (++). Título de anticuerpos elevado. Confirma diagnóstico de Ehrlichiosis. Iniciar doxiciclina 10mg/kg c/24hs por 28 días. Control de hemograma a los 14 días.',
                false,
            ],
            [
                4,
                3,
                'Otros',
                'Radiografía de tórax. Sin evidencia de fracturas costales. Parénquima pulmonar normal. Silueta cardíaca conservada. Solicitada tras trauma por caída. Descarta lesiones intratorácicas.',
                false,
            ],
            [
                1,
                19,
                'Parasitología',
                'Raspado cutáneo. Presencia de ácaros Sarcoptes scabiei en muestra analizada. Moderada cantidad. Confirma diagnóstico de sarna sarcóptica. Continuar protocolo de ivermectina. Tratar todos los animales en contacto.',
                true,
            ],
            [
                10,
                8,
                'Bioquímica',
                'Perfil bioquímico hepático. ALT: 189U/L (elevada) | AST: 145U/L (elevada) | Bilirrubina total: 2.8mg/dL | Proteínas totales: 5.2g/dL | Albumina: 2.1g/dL. Compatible con lipidosis hepática felina. Iniciar tratamiento de soporte hepático de inmediato.',
                true,
            ],
            [
                12,
                15,
                'Uroanálisis',
                'Urinálisis y sedimento. pH: 7.5 | Densidad: 1.038 | Proteínas: + | Cristales de estruvita: +++ | Leucocitos: 8-10/campo. Cristaluria severa por estruvita. Cistitis secundaria. Confirma necesidad de dieta acidificante y aumento de ingesta hídrica.',
                true,
            ],
            [
                6,
                10,
                'Prueba Rápida',
                'Test rápido Parvovirus. Positivo. Confirma parvovirus canino. Se mantiene hospitalización y tratamiento de soporte. Pronóstico reservado.',
                true,
            ],
        ];

        foreach ($tests as $i => [$petIdx, $daysAgo, $type, $observation, $withResult]) {
            $pet = $pets[$petIdx % $pets->count()];

            Test::updateOrCreate(['pet_id' => $pet->id, 'type' => $type], [
                'date' => now()->subDays($daysAgo)->toDateString(),
                'result' => $withResult ? [$this->storeSampleResult($pet->id, $type)] : null,
                'observation' => $observation,
                'user_id' => $vets[$i % $vets->count()]->id,
            ]);
        }
    }

    /**
     * Guarda un PDF de ejemplo en el disco privado de resultados y devuelve su ruta.
     */
    private function storeSampleResult(int $petId, string $type): string
    {
        $path = 'resultados/demo-'.$petId.'-'.Str::slug($type).'.pdf';

        Storage::disk('test-results')->put($path, $this->samplePdf('Resultado de ejemplo (datos demo) - '.Str::ascii($type)));

        return $path;
    }

    /**
     * PDF mínimo de una página con una línea de texto (sin dependencias).
     */
    private function samplePdf(string $text): string
    {
        $text = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $text);
        $stream = "BT /F1 14 Tf 50 780 Td ({$text}) Tj ET";

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];

        foreach ($objects as $n => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($n + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xref = strlen($pdf);
        $pdf .= 'xref\n0 '.(count($objects) + 1)."\n0000000000 65535 f \n";

        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf.'trailer << /Size '.(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }
}
