<?php

use App\Filament\Actions\DownloadExport;
use App\Filament\Resources\ExportLogResource;
use App\Filament\Resources\ExportLogResource\Pages\ListExportLogs;
use App\Filament\Resources\OwnerResource\Pages\ListOwners;
use App\Filament\Resources\PetResource\Pages\ListPets;
use App\Filament\Resources\SurgeryResource\Pages\ListSurgeries;
use App\Models\ExportLog;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

it('veterinarios y asistentes pueden exportar por defecto', function (string $role) {
    actingAsRole($role);

    Livewire::test(ListPets::class)->assertActionVisible('export');
})->with(['veterinarian', 'assistant']);

it('un rol sin el permiso no ve Exportar', function () {
    $role = Role::create(['name' => 'solo-lectura', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_pet', 'view_pet']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    Livewire::test(ListPets::class)->assertActionHidden('export');
});

it('la migración da export_data a veterinarios y asistentes sin pisar nada', function () {
    $roles = ['veterinarian', 'assistant'];
    foreach ($roles as $name) {
        Role::findByName($name)->revokePermissionTo('export_data');
    }

    $migration = require database_path('migrations/2026_10_27_000001_grant_export_permissions.php');
    $migration->up();
    $migration->up(); // idempotente

    foreach ($roles as $name) {
        expect(Role::findByName($name)->hasPermissionTo('export_data'))->toBeTrue();
    }
    expect(Permission::where('name', 'view_export_log')->exists())->toBeTrue();
});

it('cada exportación deja un registro con usuario, lista, filas y formato', function () {
    $user = actingAsRole('veterinarian');
    Surgery::factory()->count(2)->create();

    Livewire::test(ListSurgeries::class)
        ->mountAction('export')
        ->set('mountedActionsData.0.format', 'csv')
        ->set('mountedActionsData.0.columns', ['type', 'date'])
        ->callMountedAction()
        ->assertFileDownloaded();

    $log = ExportLog::query()->latest('id')->firstOrFail();

    expect($log->user_id)->toBe($user->id)
        ->and($log->user_name)->toBe($user->name)
        ->and($log->list)->toBe('Cirugías')
        ->and($log->rows)->toBe(2)
        ->and($log->format)->toBe('csv')
        ->and($log->columns)->toEqualCanonicalizing(['type', 'date'])
        ->and($log->includes_owner_data)->toBeFalse();
});

it('marca cuando el archivo trae datos de propietarios', function () {
    actingAsRole('admin');
    Pet::factory()->create();

    Livewire::test(ListPets::class)
        ->mountAction('export')
        ->set('mountedActionsData.0.format', 'csv')
        ->set('mountedActionsData.0.columns', ['name', 'owner.phone'])
        ->callMountedAction();

    expect(ExportLog::query()->latest('id')->first()->includes_owner_data)->toBeTrue();

    Livewire::test(ListOwners::class)
        ->mountAction('export')
        ->set('mountedActionsData.0.format', 'csv')
        ->set('mountedActionsData.0.columns', ['id'])
        ->callMountedAction();

    expect(ExportLog::query()->latest('id')->first()->includes_owner_data)->toBeTrue();
});

it('una exportación rechazada por el tope de filas no deja registro', function () {
    actingAsRole('admin');
    DownloadExport::$maxRows = 1;
    Surgery::factory()->count(2)->create();

    Livewire::test(ListSurgeries::class)
        ->callAction('export', data: ['format' => 'csv', 'columns' => ['type']]);

    expect(ExportLog::count())->toBe(0);

    DownloadExport::$maxRows = 5000;
});

it('solo admin ve el registro de exportaciones', function () {
    ExportLog::create(['user_name' => 'Alguien', 'list' => 'Mascotas', 'rows' => 3, 'format' => 'xlsx', 'columns' => ['name'], 'includes_owner_data' => false]);

    actingAsRole('admin');
    expect(ExportLogResource::canViewAny())->toBeTrue();
    Livewire::test(ListExportLogs::class)->assertSuccessful()->assertSee('Alguien');

    foreach (['veterinarian', 'assistant'] as $role) {
        actingAsRole($role);
        expect(ExportLogResource::canViewAny())->toBeFalse();
    }
});

it('el registro es de solo lectura', function () {
    actingAsRole('admin');
    $log = ExportLog::create(['user_name' => 'Alguien', 'list' => 'Mascotas', 'rows' => 3, 'format' => 'xlsx', 'columns' => ['name'], 'includes_owner_data' => false]);

    expect(ExportLogResource::canCreate())->toBeFalse()
        ->and(ExportLogResource::canEdit($log))->toBeFalse()
        ->and(ExportLogResource::canDelete($log))->toBeFalse();
});

it('conserva el registro aunque se elimine el usuario', function () {
    $user = User::factory()->create();
    $log = ExportLog::create(['user_id' => $user->id, 'user_name' => $user->name, 'list' => 'Mascotas', 'rows' => 1, 'format' => 'csv', 'columns' => ['name'], 'includes_owner_data' => false]);

    $user->delete();

    expect($log->fresh()->user_id)->toBeNull()
        ->and($log->fresh()->user_name)->toBe($user->name);
});
