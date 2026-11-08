<?php

use App\Filament\Concerns\ClinicRoles;
use App\Models\Branch;
use App\Models\Consultation;
use App\Models\Owner;
use App\Models\Pet;
use App\Models\PetWeight;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use App\Support\BranchContext;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

/** Una fila de cada tabla clínica que anota sucursal, creada con las factories. */
dataset('clinical models', [
    'propietario' => [fn () => Owner::factory()->create()],
    'mascota' => [fn () => Pet::factory()->create()],
    'consulta' => [fn () => Consultation::factory()->create()],
    'vacuna' => [fn () => Vaccination::factory()->create()],
    'cirugía' => [fn () => Surgery::factory()->create()],
    'examen' => [fn () => Test::factory()->create()],
    'peso' => [fn () => PetWeight::factory()->create()],
]);

function adminWithoutBranch(): User
{
    $admin = User::factory()->create(['branch_id' => null]);
    $admin->assignRole(ClinicRoles::ADMIN);

    return $admin;
}

it('anota la sucursal principal en los registros nuevos sin sesión', function (Closure $make) {
    expect($make()->branch_id)->toBe(Branch::main()->id);
})->with('clinical models');

it('anota la sucursal del usuario en los registros que crea', function () {
    $branch = Branch::factory()->create();
    $vet = User::factory()->create(['branch_id' => $branch->id]);
    $vet->assignRole(ClinicRoles::VETERINARIAN);

    $this->actingAs($vet);

    expect(Owner::factory()->create()->branch_id)->toBe($branch->id);
});

it('respeta la sucursal indicada al crear el registro', function () {
    $branch = Branch::factory()->create();

    expect(Owner::factory()->create(['branch_id' => $branch->id])->branch_id)->toBe($branch->id);
});

it('el administrador sin sucursal elegida usa la principal', function () {
    $this->actingAs(adminWithoutBranch());

    expect(BranchContext::currentId())->toBe(Branch::main()->id);
});

it('el administrador usa la sucursal que eligió', function () {
    $branch = Branch::factory()->create();
    $this->actingAs(adminWithoutBranch());

    BranchContext::switchTo($branch);

    expect(BranchContext::currentId())->toBe($branch->id)
        ->and(Owner::factory()->create()->branch_id)->toBe($branch->id);
});

it('vuelve a la principal si la sucursal elegida se deshabilitó', function () {
    $branch = Branch::factory()->create();
    $this->actingAs(adminWithoutBranch());
    BranchContext::switchTo($branch);

    $branch->update(['is_active' => false]);

    expect(BranchContext::currentId())->toBe(Branch::main()->id);
});

it('no permite elegir una sucursal deshabilitada ni inexistente', function () {
    $inactive = Branch::factory()->inactive()->create();
    $this->actingAs(adminWithoutBranch());

    expect(fn () => BranchContext::switchTo($inactive))->toThrow(HttpException::class)
        ->and(fn () => BranchContext::switchTo(999999))->toThrow(HttpException::class);
});

it('el personal con sucursal ignora la elección guardada en sesión', function () {
    $own = Branch::factory()->create();
    $other = Branch::factory()->create();
    $vet = User::factory()->create(['branch_id' => $own->id]);
    $this->actingAs($vet);

    session()->put(BranchContext::SESSION_KEY, $other->id);

    expect(BranchContext::currentId())->toBe($own->id);
});

it('olvida la elección con forget', function () {
    $branch = Branch::factory()->create();
    $this->actingAs(adminWithoutBranch());
    BranchContext::switchTo($branch);

    BranchContext::forget();

    expect(BranchContext::currentId())->toBe(Branch::main()->id);
});

it('filtra los registros por sucursal con el scope inBranch', function () {
    $branch = Branch::factory()->create();
    $inBranch = Owner::factory()->create(['branch_id' => $branch->id]);
    $inMain = Owner::factory()->create();

    $ids = Owner::query()->inBranch($branch)->pluck('id');

    expect($ids)->toContain($inBranch->id)
        ->and($ids)->not->toContain($inMain->id);
});

it('la migración asigna la sucursal principal a lo que no tiene y no pisa lo demás', function () {
    $branch = Branch::factory()->create();
    $orphan = Owner::factory()->create();
    $kept = Owner::factory()->create(['branch_id' => $branch->id]);
    DB::table('owners')->where('id', $orphan->id)->update(['branch_id' => null]);

    $migration = require database_path('migrations/2026_11_08_100000_add_branch_id_to_clinical_tables.php');
    $migration->up();
    $migration->up();

    expect($orphan->fresh()->branch_id)->toBe(Branch::main()->id)
        ->and($kept->fresh()->branch_id)->toBe($branch->id);
});

it('una sucursal con registros clínicos no puede eliminarse', function () {
    $branch = Branch::factory()->create();
    Owner::factory()->create(['branch_id' => $branch->id]);

    expect($branch->deletionBlockedReason())->toContain('registros');
});
