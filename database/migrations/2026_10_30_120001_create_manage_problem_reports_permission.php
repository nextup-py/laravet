<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea el permiso que habilita la lista de reportes de problemas (solo admin por defecto,
 * por ser Super Admin de Shield). Vive en una migración porque el deploy no corre los
 * seeders; es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'manage_problem_reports', 'guard_name' => 'web']);

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where(['name' => 'manage_problem_reports', 'guard_name' => 'web'])->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
