<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Tablas clínicas que anotan la sucursal donde se registró cada fila. */
    private const TABLES = [
        'owners',
        'pets',
        'consultations',
        'vaccinations',
        'surgeries',
        'tests',
        'pet_weights',
    ];

    /**
     * Agrega `branch_id` a las tablas clínicas y asigna la sucursal principal (`001`) a lo que ya existe.
     * Idempotente: el deploy corre las migraciones sin seeders.
     */
    public function up(): void
    {
        foreach (self::TABLES as $tableName) {
            if (Schema::hasColumn($tableName, 'branch_id')) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) {
                // restrictOnDelete: una sucursal con registros no se elimina por accidente.
                $table->foreignId('branch_id')->nullable()->constrained('branches')->restrictOnDelete();
            });
        }

        $mainId = DB::table('branches')->where('code', '001')->value('id');

        if (! $mainId) {
            return;
        }

        foreach (self::TABLES as $tableName) {
            DB::table($tableName)->whereNull('branch_id')->update(['branch_id' => $mainId]);
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::TABLES) as $tableName) {
            if (Schema::hasColumn($tableName, 'branch_id')) {
                Schema::table($tableName, function (Blueprint $table) {
                    $table->dropConstrainedForeignId('branch_id');
                });
            }
        }
    }
};
