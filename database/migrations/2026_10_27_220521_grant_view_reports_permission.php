<?php

use App\Filament\Concerns\ClinicRoles;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Crea el permiso que habilita la sección Reportes y se lo da por defecto a veterinarios y
 * asistentes (el admin lo tiene por ser Super Admin). Vive en una migración porque el deploy no
 * corre los seeders; es idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permission = Permission::firstOrCreate(['name' => 'view_reports', 'guard_name' => 'web']);

        foreach ([ClinicRoles::VETERINARIAN, ClinicRoles::ASSISTANT] as $name) {
            Role::where(['name' => $name, 'guard_name' => 'web'])->first()?->givePermissionTo($permission);
        }

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::where(['name' => 'view_reports', 'guard_name' => 'web'])->delete();
        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
};
