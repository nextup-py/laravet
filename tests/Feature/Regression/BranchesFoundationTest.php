<?php

use App\Filament\Concerns\ClinicRoles;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\QueryException;

/** Ejecuta de nuevo la migración de sucursales, como lo hace un deploy sobre una base que ya la corrió. */
function runBranchesMigration(): void
{
    $migration = require database_path('migrations/2026_11_07_100000_create_branches_table.php');
    $migration->up();
}

it('crea la sucursal principal con código 001', function () {
    $main = Branch::main();

    expect($main)->not->toBeNull()
        ->and($main->code)->toBe('001')
        ->and($main->name)->toBe('Casa central')
        ->and($main->is_active)->toBeTrue();
});

it('vuelve a correr la migración sin duplicar la sucursal principal', function () {
    runBranchesMigration();
    runBranchesMigration();

    expect(Branch::query()->where('code', '001')->count())->toBe(1);
});

it('asigna la sucursal principal al personal sin sucursal pero no al administrador', function () {
    $vet = User::factory()->create();
    $vet->assignRole(ClinicRoles::VETERINARIAN);
    $assistant = User::factory()->create();
    $assistant->assignRole(ClinicRoles::ASSISTANT);
    $admin = User::factory()->create();
    $admin->assignRole(ClinicRoles::ADMIN);

    runBranchesMigration();

    $mainId = Branch::main()->id;

    expect($vet->fresh()->branch_id)->toBe($mainId)
        ->and($assistant->fresh()->branch_id)->toBe($mainId)
        ->and($admin->fresh()->branch_id)->toBeNull();
});

it('no cambia la sucursal de un usuario que ya tiene una', function () {
    $other = Branch::factory()->create();
    $vet = User::factory()->create(['branch_id' => $other->id]);
    $vet->assignRole(ClinicRoles::VETERINARIAN);

    runBranchesMigration();

    expect($vet->fresh()->branch_id)->toBe($other->id);
});

it('relaciona al usuario con su sucursal y la sucursal con su personal', function () {
    $branch = Branch::factory()->create();
    $user = User::factory()->create(['branch_id' => $branch->id]);

    expect($user->branch->is($branch))->toBeTrue()
        ->and($branch->users->pluck('id')->all())->toBe([$user->id]);
});

it('no admite dos sucursales con el mismo código', function () {
    Branch::factory()->create(['code' => '002']);

    expect(fn () => Branch::factory()->create(['code' => '002']))->toThrow(QueryException::class);
});

it('solo lista las sucursales habilitadas con el scope active', function () {
    $inactive = Branch::factory()->inactive()->create();

    expect(Branch::query()->active()->pluck('id'))->not->toContain($inactive->id)
        ->and(Branch::query()->active()->pluck('id'))->toContain(Branch::main()->id);
});

it('explica por qué una sucursal no puede eliminarse', function () {
    $free = Branch::factory()->create();
    $withStaff = Branch::factory()->create();
    User::factory()->create(['branch_id' => $withStaff->id]);

    expect(Branch::main()->deletionBlockedReason())->toContain('principal')
        ->and($withStaff->deletionBlockedReason())->toContain('personal')
        ->and($free->deletionBlockedReason())->toBeNull();
});

it('impide borrar de la base una sucursal con personal asignado', function () {
    $branch = Branch::factory()->create();
    User::factory()->create(['branch_id' => $branch->id]);

    expect(fn () => $branch->delete())->toThrow(QueryException::class);
});
