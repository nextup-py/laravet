<?php

namespace App\Http\Controllers;

use App\Models\ProblemReport;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega la captura de un reporte de problema solo a quien administra los reportes
 * (permiso `manage_problem_reports`). Solo se muestran PNG y JPG; cualquier otro tipo se descarga.
 */
class ProblemReportAttachmentController extends Controller
{
    private const INLINE_MIME_TYPES = ['image/png', 'image/jpeg'];

    public function __invoke(ProblemReport $report): StreamedResponse
    {
        abort_unless(auth()->user()?->can('manage_problem_reports'), 403);

        $disk = Storage::disk('problem-reports');

        abort_unless($report->attachment && $disk->exists($report->attachment), 404);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ];

        if (in_array($disk->mimeType($report->attachment), self::INLINE_MIME_TYPES, true)) {
            return $disk->response($report->attachment, basename($report->attachment), $headers);
        }

        return $disk->download($report->attachment, basename($report->attachment), $headers);
    }
}
