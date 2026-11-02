<?php

use App\Enums\PetWeightSource;
use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Filament\Resources\PetResource\RelationManagers\WeightsRelationManager;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\PetWeight;
use App\Models\User;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

function pesos(Pet $pet): Testable
{
    return Livewire::test(WeightsRelationManager::class, ['ownerRecord' => $pet, 'pageClass' => ViewPet::class]);
}

it('lista los pesajes con su origen, del más reciente al más antiguo', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['weight' => 10]);
    $pet->weights()->update(['weighed_at' => today()->subMonths(3)->toDateString()]);
    $suelto = PetWeight::factory()->create(['pet_id' => $pet->id, 'weight' => 11, 'weighed_at' => today()->subMonth()->toDateString()]);
    $consulta = Consultation::factory()->create(['pet_id' => $pet->id, 'consultation_date' => today()->subDays(2)->toDateString(), 'weight' => 12]);
    $deConsulta = PetWeight::where('consultation_id', $consulta->id)->first();
    $registro = $pet->weights()->where('source', PetWeightSource::Registration)->first();

    pesos($pet)
        ->assertCanSeeTableRecords([$deConsulta, $suelto, $registro], inOrder: true)
        ->assertSee('Registro')
        ->assertSee('Consulta')
        ->assertSee('Pesaje');
});

it('el pesaje de la consulta se corrige en la consulta: no se edita aquí', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();
    $consulta = Consultation::factory()->create(['pet_id' => $pet->id, 'weight' => 9.5]);
    $deConsulta = PetWeight::where('consultation_id', $consulta->id)->first();
    $suelto = PetWeight::factory()->create(['pet_id' => $pet->id]);

    pesos($pet)
        ->assertTableActionHidden('edit', $deConsulta)
        ->assertTableActionVisible('edit', $suelto)
        ->assertTableActionVisible('openConsultation', $deConsulta);
});

it('registrar un peso desde la pestaña lo deja como pesaje y actualiza el peso actual', function () {
    $admin = actingAsRole('admin');
    $pet = Pet::factory()->create(['weight' => 10]);

    pesos($pet)
        ->callTableAction('create', data: ['weighed_at' => today()->toDateString(), 'weight' => 11.5])
        ->assertHasNoTableActionErrors();

    $nuevo = $pet->weights()->latest('id')->first();
    expect($nuevo->source)->toBe(PetWeightSource::Weighing)
        ->and($nuevo->user_id)->toBe($admin->id)
        ->and((float) $pet->fresh()->weight)->toBe(11.5);
});

it('corregir un pesaje por error recalcula el peso actual', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['weight' => 10]);
    $errado = PetWeight::factory()->create(['pet_id' => $pet->id, 'weight' => 150, 'weighed_at' => today()->addDay()->toDateString()]);
    expect((float) $pet->fresh()->weight)->toBe(150.0);

    pesos($pet)
        ->callTableAction('edit', $errado, data: ['weighed_at' => today()->toDateString(), 'weight' => 15])
        ->assertHasNoTableActionErrors();

    expect((float) $pet->fresh()->weight)->toBe(15.0);

    $errado->delete();
    expect((float) $pet->fresh()->weight)->toBe(10.0);
});

it('rechaza pesos fuera de rango y fechas futuras', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    pesos($pet)
        ->callTableAction('create', data: ['weighed_at' => today()->addDay()->toDateString(), 'weight' => 400])
        ->assertHasTableActionErrors(['weighed_at', 'weight']);
});

it('no se puede eliminar el único pesaje que queda ni los de consulta', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();
    $unico = $pet->weights()->first();

    $manager = pesos($pet)->instance();
    $canDelete = new ReflectionMethod($manager, 'canDelete');

    expect($canDelete->invoke($manager, $unico))->toBeFalse();

    $otro = PetWeight::factory()->create(['pet_id' => $pet->id]);
    expect($canDelete->invoke($manager, $otro))->toBeTrue();

    $consulta = Consultation::factory()->create(['pet_id' => $pet->id, 'weight' => 8]);
    expect($canDelete->invoke($manager, PetWeight::where('consultation_id', $consulta->id)->first()))->toBeFalse();
});

it('sin permiso de editar mascotas la pestaña es de solo lectura', function () {
    $viewer = User::factory()->create();
    $role = Role::create(['name' => 'solo-lectura-pesos']);
    $role->givePermissionTo(['view_any_pet', 'view_pet']);
    $viewer->assignRole($role);
    $this->actingAs($viewer);
    $pet = Pet::factory()->create();

    pesos($pet)
        ->assertTableActionHidden('create')
        ->assertTableActionHidden('edit', $pet->weights()->first());
});
