<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;

/**
 * Permiso personalizado que habilita exportar las listas del panel. Es idempotente y vive en una
 * migración porque el deploy no corre los seeders: sin esto el permiso no existiría en producción.
 */
return new class extends Migration
{
    public function up(): void
    {
        Permission::firstOrCreate(['name' => 'export_data', 'guard_name' => 'web']);
    }

    public function down(): void
    {
        Permission::where(['name' => 'export_data', 'guard_name' => 'web'])->delete();
    }
};
