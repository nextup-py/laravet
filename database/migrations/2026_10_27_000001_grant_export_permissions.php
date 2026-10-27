<?php

use App\Filament\Concerns\ClinicRoles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Exportar queda disponible por defecto para veterinarios y asistentes, y se crea el permiso que
 * habilita el registro de exportaciones (solo admin lo ve, por ser Super Admin). Vive en una migración
 * porque el deploy no corre los seeders; es idempotente y no quita nada a nadie.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $exportData = Permission::firstOrCreate(['name' => 'export_data', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'view_export_log', 'guard_name' => 'web']);

        foreach ([ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT] as $name) {
            Role::where(['name' => $name, 'guard_name' => 'web'])->first()?->givePermissionTo($exportData);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        foreach ([ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT] as $name) {
            Role::where(['name' => $name, 'guard_name' => 'web'])->first()?->revokePermissionTo('export_data');
        }

        Permission::where(['name' => 'view_export_log', 'guard_name' => 'web'])->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
