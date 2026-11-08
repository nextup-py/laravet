<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea los permisos de las sucursales (solo admin por defecto, por ser Super Admin de Shield).
 * Vive en una migración porque el deploy no corre los seeders; es idempotente.
 */
return new class extends Migration
{
    private const ABILITIES = ['view', 'view_any', 'create', 'update', 'delete', 'delete_any'];

    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::ABILITIES as $ability) {
            Permission::firstOrCreate(['name' => "{$ability}_branch", 'guard_name' => 'web']);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach (self::ABILITIES as $ability) {
            Permission::where(['name' => "{$ability}_branch", 'guard_name' => 'web'])->delete();
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
