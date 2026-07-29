<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Los valores existentes en `result` son texto plano (no JSON válido),
        // ya que hasta ahora el campo guardaba una descripción del resultado en
        // vez de rutas de archivos. Se preservan al inicio de `observation` antes
        // de convertir la columna, para no perder datos ya cargados en producción
        // y evitar que MySQL rechace el ALTER por JSON inválido.
        DB::table('tests')
            ->whereNotNull('result')
            ->where('result', '!=', '')
            ->get()
            ->each(function ($row) {
                DB::table('tests')->where('id', $row->id)->update([
                    'observation' => trim($row->result.("\n\n".($row->observation ?? ''))),
                    'result' => null,
                ]);
            });

        Schema::table('tests', function (Blueprint $table) {
            $table->json('result')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tests', function (Blueprint $table) {
            $table->string('result')->nullable()->change();
        });
    }
};
