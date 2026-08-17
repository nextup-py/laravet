<?php

namespace Database\Seeders;

use App\Filament\Concerns\ClinicRoles;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Roles dinámicos, permisos fijos: el catálogo de permisos (qué acciones existen)
 * se define acá y con `php artisan shield:generate` (ver CLAUDE.md); un admin puede
 * crear roles nuevos y armar combinaciones de estos permisos desde el panel
 * ("Configuración → Roles y permisos"), sin tocar código.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Modelos con Resource de Filament + las 12 habilidades estándar que genera
     * `php artisan shield:generate`. El modelo `role` (el propio RoleResource de
     * Shield) solo soporta 6: no tiene soft deletes ni replicate/reorder/restore.
     */
    private const RESOURCE_MODELS = [
        'city', 'consultation', 'department', 'neighborhood', 'owner',
        'pet', 'surgery', 'test', 'user', 'vaccination',
    ];

    private const RESOURCE_ABILITIES = [
        'view', 'view_any', 'create', 'update', 'restore', 'restore_any',
        'replicate', 'reorder', 'delete', 'delete_any', 'force_delete', 'force_delete_any',
    ];

    private const ROLE_MODEL_ABILITIES = ['view', 'view_any', 'create', 'update', 'delete', 'delete_any'];

    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (self::RESOURCE_MODELS as $model) {
            foreach (self::RESOURCE_ABILITIES as $ability) {
                Permission::firstOrCreate(['name' => "{$ability}_{$model}", 'guard_name' => 'web']);
            }
        }

        foreach (self::ROLE_MODEL_ABILITIES as $ability) {
            Permission::firstOrCreate(['name' => "{$ability}_role", 'guard_name' => 'web']);
        }

        Permission::firstOrCreate(['name' => 'use_ai_diagnostics', 'guard_name' => 'web']);

        // admin = Super Admin (config/filament-shield.php): bypassea todos los
        // permisos, no necesita asignación explícita.
        Role::firstOrCreate(['name' => ClinicRoles::ADMIN, 'guard_name' => 'web']);

        $veterinarian = Role::firstOrCreate(['name' => ClinicRoles::VETERINARIAN, 'guard_name' => 'web']);
        $veterinarian->syncPermissions([
            ...$this->crud('owner'),
            ...$this->crud('pet'),
            ...$this->crud('consultation'),
            ...$this->crud('surgery'),
            ...$this->crud('test'),
            ...$this->crud('vaccination'),
            'use_ai_diagnostics',
        ]);

        $assistant = Role::firstOrCreate(['name' => ClinicRoles::ASSISTANT, 'guard_name' => 'web']);
        $assistant->syncPermissions([
            ...$this->crud('owner', canDelete: false),
            ...$this->crud('pet', canDelete: false),
            ...$this->view('consultation'),
            ...$this->view('surgery'),
            ...$this->view('test'),
            ...$this->view('vaccination'),
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function crud(string $model, bool $canDelete = true): array
    {
        $abilities = ['view', 'view_any', 'create', 'update'];

        if ($canDelete) {
            $abilities[] = 'delete';
        }

        return array_map(fn (string $ability): string => "{$ability}_{$model}", $abilities);
    }

    /**
     * @return array<int, string>
     */
    private function view(string $model): array
    {
        return ["view_{$model}", "view_any_{$model}"];
    }
}
