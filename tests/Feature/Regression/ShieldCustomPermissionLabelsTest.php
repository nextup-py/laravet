<?php

use App\Filament\Resources\RoleResource;
use App\Filament\Resources\RoleResource\Pages\CreateRole;
use App\Filament\Resources\RoleResource\Pages\EditRole;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('el permiso de IA se muestra con su etiqueta en español', function () {
    expect(RoleResource::getCustomPermissionOptions())
        ->toHaveKey('use_ai_diagnostics', 'Usar diagnóstico asistido por IA')
        ->and(collect(RoleResource::getCustomPermissionOptions())->values()->implode(' '))->not->toContain('Use Ai Diagnostics');
});

it('las etiquetas de permisos personalizados vienen de lang/es/permissions.php y las desconocidas usan headline', function () {
    $etiquetas = trans('permissions.custom');

    expect($etiquetas)->toBeArray()->toHaveKey('use_ai_diagnostics')
        ->and(__('permissions.custom.use_ai_diagnostics'))->toBe('Usar diagnóstico asistido por IA');

    Permission::firstOrCreate(['name' => 'exportar_historias', 'guard_name' => 'web']);

    expect(RoleResource::getCustomPermissionOptions())
        ->toHaveKey('exportar_historias', 'Exportar Historias');
});

it('el formulario de crear y editar roles muestra la etiqueta en español', function () {
    actingAsRole('admin');

    Livewire::test(CreateRole::class)
        ->assertSee('Usar diagnóstico asistido por IA')
        ->assertDontSee('Use Ai Diagnostics');

    $rol = Role::findByName('veterinarian');

    Livewire::test(EditRole::class, ['record' => $rol->getRouteKey()])
        ->assertSee('Usar diagnóstico asistido por IA');
});

it('el RoleResource publicado en app es el único registrado y marcar el permiso en un rol sigue funcionando', function () {
    actingAsRole('admin');
    $rol = Role::create(['name' => 'auxiliar', 'guard_name' => 'web']);

    expect(config('filament-shield.shield_resource.slug'))->toBe('roles')
        ->and(collect(Filament::getCurrentPanel()->getResources())->filter(fn ($r) => str_ends_with($r, '\\RoleResource'))->all())
        ->toHaveCount(1)
        ->toContain(RoleResource::class);

    Livewire::test(EditRole::class, ['record' => $rol->getRouteKey()])
        ->fillForm(['name' => 'auxiliar', 'custom_permissions' => ['use_ai_diagnostics']])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($rol->fresh()->hasPermissionTo('use_ai_diagnostics'))->toBeTrue();
});
