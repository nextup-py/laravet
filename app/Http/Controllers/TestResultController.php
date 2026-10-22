<?php

namespace App\Http\Controllers;

use App\Models\Test;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Entrega un archivo de resultado de una prueba laboratorial solo a usuarios con permiso para ver pruebas.
 */
class TestResultController extends Controller
{
    /**
     * Discos donde puede estar el archivo: el privado actual y el público anterior
     * (por si todavía no se migraron los archivos antiguos).
     */
    private const DISKS = ['test-results', 'public'];

    /**
     * Tipos que se muestran en el navegador. Cualquier otro (HTML, SVG, XML, etc.)
     * se fuerza como descarga para que no pueda ejecutar código en este dominio.
     */
    public const INLINE_MIME_TYPES = ['application/pdf', 'image/png', 'image/jpeg'];

    public function __invoke(Test $test, int $index): StreamedResponse
    {
        Gate::authorize('view', $test);

        $path = array_values($test->result ?? [])[$index] ?? null;

        abort_unless(is_string($path), 404);

        foreach (self::DISKS as $disk) {
            if (! Storage::disk($disk)->exists($path)) {
                continue;
            }

            $headers = [
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; sandbox",
            ];

            if (in_array(Storage::disk($disk)->mimeType($path), self::INLINE_MIME_TYPES, true)) {
                // El visor de PDF del navegador no funciona con "sandbox", y estos tipos no ejecutan código.
                unset($headers['Content-Security-Policy']);

                return Storage::disk($disk)->response($path, basename($path), $headers);
            }

            return Storage::disk($disk)->download($path, basename($path), $headers);
        }

        abort(404);
    }
}
