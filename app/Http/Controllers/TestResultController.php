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

    public function __invoke(Test $test, int $index): StreamedResponse
    {
        Gate::authorize('view', $test);

        $path = array_values($test->result ?? [])[$index] ?? null;

        abort_unless(is_string($path), 404);

        foreach (self::DISKS as $disk) {
            if (Storage::disk($disk)->exists($path)) {
                return Storage::disk($disk)->response($path, basename($path));
            }
        }

        abort(404);
    }
}
