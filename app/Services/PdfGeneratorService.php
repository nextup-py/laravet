<?php

namespace App\Services;

use App\Settings\ClinicSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Symfony\Component\HttpFoundation\Response;

class PdfGeneratorService
{
    /**
     * Respuesta con el PDF en línea (`Content-Disposition: inline`): el navegador lo muestra en su
     * visor y desde ahí se lee, imprime o guarda. Se usa desde un controlador, no desde una acción
     * de Livewire (que solo dispara descargas con un StreamedResponse).
     */
    public function inline(string $view, array $data, string $filename): Response
    {
        $pdf = Pdf::loadView($view, [
            ...$data,
            'clinic' => app(ClinicSettings::class),
        ])->setPaper('a4', 'portrait');

        $pdf->render();
        $this->paginate($pdf);

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function paginate(PdfDocument $pdf): void
    {
        $canvas = $pdf->getDomPDF()->getCanvas();
        $font = $pdf->getDomPDF()->getFontMetrics()->getFont('helvetica', 'normal');

        $canvas->page_text(
            $canvas->get_width() - 150,
            $canvas->get_height() - 45,
            'Página {PAGE_NUM} de {PAGE_COUNT}',
            $font,
            8,
            [0.45, 0.45, 0.45],
        );
    }
}
