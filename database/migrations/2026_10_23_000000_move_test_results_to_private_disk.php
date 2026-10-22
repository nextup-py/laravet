<?php

use App\Models\Test;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Los resultados de las pruebas laboratoriales se guardaban en el disco público
 * (accesibles por URL sin iniciar sesión). Esta migración los mueve al disco
 * privado `test-results`. Es idempotente: si el archivo ya está en el disco
 * privado o no existe en el público, no hace nada con él.
 */
return new class extends Migration
{
    public function up(): void
    {
        $public = Storage::disk('public');
        $private = Storage::disk('test-results');

        Test::query()->whereNotNull('result')->each(function (Test $test) use ($public, $private) {
            foreach ((array) $test->result as $path) {
                if (! is_string($path) || ! $public->exists($path)) {
                    continue;
                }

                if (! $private->exists($path)) {
                    $private->put($path, $public->get($path));
                }

                $public->delete($path);
            }
        });
    }

    public function down(): void
    {
        // Los archivos no se devuelven al disco público a propósito.
    }
};
