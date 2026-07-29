<?php

namespace App\Services;

use App\Settings\ClinicSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as PdfDocument;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PdfGeneratorService
{
    /**
     * Livewire solo dispara la descarga en el navegador para acciones que
     * devuelven un StreamedResponse (ver SupportFileDownloads de Livewire) —
     * por eso se usa streamDownload() en vez del Response plano de
     * PDF::download(), volcando los bytes crudos de output() (nunca el
     * Response de stream(), que al "echo"-earse serializa headers HTTP).
     */
    public function download(string $view, array $data, string $filename): StreamedResponse
    {
        $pdf = Pdf::loadView($view, [
            ...$data,
            'clinic' => app(ClinicSettings::class),
        ])->setPaper('a4', 'portrait');

        $pdf->render();
        $this->paginate($pdf);

        return response()->streamDownload(fn () => print ($pdf->output()), $filename);
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
