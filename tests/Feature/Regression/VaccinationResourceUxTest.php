<?php

use App\Enums\PetSpecies;
use App\Enums\VaccinationStatus;
use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Filament\Resources\PetResource\RelationManagers\VaccinationsRelationManager;
use App\Filament\Resources\VaccinationResource;
use App\Filament\Resources\VaccinationResource\Pages\CreateVaccination;
use App\Filament\Resources\VaccinationResource\Pages\EditVaccination;
use App\Filament\Resources\VaccinationResource\Pages\ListVaccinations;
use App\Filament\Resources\VaccinationResource\Pages\ViewVaccination;
use App\Models\Pet;
use App\Models\User;
use App\Models\Vaccination;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('calcula el estado según la fecha de próxima aplicación', function () {
    expect(Vaccination::factory()->make(['next_application' => today()->subDay()])->status)->toBe(VaccinationStatus::Overdue)
        ->and(Vaccination::factory()->make(['next_application' => today()])->status)->toBe(VaccinationStatus::DueSoon)
        ->and(Vaccination::factory()->make(['next_application' => today()->addDays(7)])->status)->toBe(VaccinationStatus::DueSoon)
        ->and(Vaccination::factory()->make(['next_application' => today()->addDays(8)])->status)->toBe(VaccinationStatus::UpToDate);
});

it('los scopes de estado son consistentes con el accessor', function () {
    $vencida = Vaccination::factory()->create(['next_application' => today()->subDays(3)]);
    $porVencer = Vaccination::factory()->create(['next_application' => today()->addDays(2)]);
    $alDia = Vaccination::factory()->create(['next_application' => today()->addMonths(6)]);

    expect(Vaccination::overdue()->pluck('id')->all())->toBe([$vencida->id])
        ->and(Vaccination::dueSoon()->pluck('id')->all())->toBe([$porVencer->id])
        ->and(Vaccination::upToDate()->pluck('id')->all())->toBe([$alDia->id]);
});

it('el filtro unificado de estado separa vencidas, por vencer y al día', function () {
    actingAsRole('admin');
    $vencida = Vaccination::factory()->create(['next_application' => today()->subDays(3)]);
    $porVencer = Vaccination::factory()->create(['next_application' => today()->addDays(2)]);
    $alDia = Vaccination::factory()->create(['next_application' => today()->addMonths(6)]);

    Livewire::test(ListVaccinations::class)
        ->filterTable('status', 'overdue')
        ->assertCanSeeTableRecords([$vencida])
        ->assertCanNotSeeTableRecords([$porVencer, $alDia]);

    Livewire::test(ListVaccinations::class)
        ->filterTable('status', 'due_soon')
        ->assertCanSeeTableRecords([$porVencer])
        ->assertCanNotSeeTableRecords([$vencida, $alDia]);

    Livewire::test(ListVaccinations::class)
        ->filterTable('status', 'up_to_date')
        ->assertCanSeeTableRecords([$alDia])
        ->assertCanNotSeeTableRecords([$vencida, $porVencer]);
});

it('filtra vacunaciones por vacuna, fabricante y especie', function () {
    actingAsRole('admin');
    $rabia = Vaccination::factory()->for(Pet::factory()->state(['species' => PetSpecies::Canino]))
        ->create(['vaccine' => 'Rabia', 'manufacturer' => 'Zoetis']);
    $triple = Vaccination::factory()->for(Pet::factory()->state(['species' => PetSpecies::Felino]))
        ->create(['vaccine' => 'Triple Felina', 'manufacturer' => 'Virbac']);

    Livewire::test(ListVaccinations::class)
        ->filterTable('vaccine', ['Rabia'])
        ->assertCanSeeTableRecords([$rabia])
        ->assertCanNotSeeTableRecords([$triple]);

    Livewire::test(ListVaccinations::class)
        ->filterTable('manufacturer', ['Virbac'])
        ->assertCanSeeTableRecords([$triple])
        ->assertCanNotSeeTableRecords([$rabia]);

    Livewire::test(ListVaccinations::class)
        ->filterTable('species', [PetSpecies::Felino->value])
        ->assertCanSeeTableRecords([$triple])
        ->assertCanNotSeeTableRecords([$rabia]);

    Livewire::test(ListVaccinations::class)
        ->filterTable('pet_id', $rabia->pet_id)
        ->assertCanSeeTableRecords([$rabia])
        ->assertCanNotSeeTableRecords([$triple]);
});

it('la tabla muestra el propietario bajo la mascota y el estado', function () {
    actingAsRole('admin');
    $vacunacion = Vaccination::factory()->create(['next_application' => today()->subDay()]);

    Livewire::test(ListVaccinations::class)
        ->assertSee($vacunacion->pet->owner->full_name)
        ->assertSee('Vencida');
});

it('editar una vacunación vencida no falla por minDate de la próxima aplicación', function () {
    actingAsRole('admin');
    $vacunacion = Vaccination::factory()->create([
        'application_date' => today()->subYear(),
        'next_application' => today()->subDays(10),
    ]);

    Livewire::test(EditVaccination::class, ['record' => $vacunacion->getRouteKey()])
        ->fillForm(['batch' => 'LOTE-CORREGIDO'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($vacunacion->fresh()->batch)->toBe('LOTE-CORREGIDO');
});

it('al crear no se permite una próxima aplicación en el pasado ni anterior a la aplicación', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(CreateVaccination::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'vaccine' => 'Rabia',
            'application_date' => today()->toDateString(),
            'next_application' => today()->subDay()->toDateString(),
            'batch' => 'L1',
            'manufacturer' => 'Zoetis',
        ])
        ->call('create')
        ->assertHasFormErrors(['next_application']);
});

it('al elegir la fecha de aplicación se sugiere la próxima un año después', function () {
    actingAsRole('admin');
    $fecha = today()->subDays(2);

    Livewire::test(CreateVaccination::class)
        ->fillForm(['application_date' => $fecha->toDateString()])
        ->assertFormSet(['next_application' => $fecha->copy()->addYear()->toDateString()]);
});

it('revacunar precarga mascota y vacuna en el formulario de creación', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::withQueryParams(['pet_id' => $pet->id, 'vaccine' => 'Rabia'])
        ->test(CreateVaccination::class)
        ->assertFormSet(['pet_id' => (string) $pet->id, 'vaccine' => 'Rabia']);
});

it('la fila tiene la acción Revacunar con la URL de creación precargada', function () {
    actingAsRole('admin');
    $vacunacion = Vaccination::factory()->create();

    Livewire::test(ListVaccinations::class)
        ->assertTableActionExists('revaccinate');

    expect(VaccinationResource::getUrl('create', ['pet_id' => $vacunacion->pet_id, 'vaccine' => $vacunacion->vaccine]))
        ->toContain("pet_id={$vacunacion->pet_id}");
});

it('la ficha usa vacuna y mascota como título y muestra estado y recordatorio', function () {
    actingAsRole('admin');
    $vacunacion = Vaccination::factory()->recordatorioYaEnviado()->create(['next_application' => today()->subDay()]);

    Livewire::test(ViewVaccination::class, ['record' => $vacunacion->getRouteKey()])
        ->assertSee("{$vacunacion->vaccine} · {$vacunacion->pet->name}")
        ->assertSee('Vencida')
        ->assertSee('Enviado el');
});

it('la ficha no enlaza a la mascota si el usuario no puede verla', function () {
    $role = Role::create(['name' => 'solo-vacunas', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_vaccination', 'view_vaccination']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    $vacunacion = Vaccination::factory()->create();

    Livewire::test(ViewVaccination::class, ['record' => $vacunacion->getRouteKey()])
        ->assertSee($vacunacion->pet->name)
        ->assertDontSee(route('filament.admin.resources.pets.view', ['record' => $vacunacion->pet_id]));
});

it('la búsqueda global de vacunaciones incluye mascota, estado y fecha', function () {
    $vacunacion = Vaccination::factory()->create(['next_application' => today()->addMonth()]);

    expect(VaccinationResource::getGloballySearchableAttributes())->toContain('vaccine', 'batch', 'pet.name')
        ->and(VaccinationResource::getGlobalSearchResultTitle($vacunacion))->toContain($vacunacion->pet->name)
        ->and(VaccinationResource::getGlobalSearchResultDetails($vacunacion)['Estado'])->toBe('Al día');
});

it('la ficha de mascota usa el formulario y filtros compartidos de vacunaciones', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();
    $vencida = Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Rabia', 'next_application' => today()->subDays(2)]);
    $alDia = Vaccination::factory()->create(['pet_id' => $pet->id, 'vaccine' => 'Triple Felina', 'next_application' => today()->addMonths(6)]);

    Livewire::test(VaccinationsRelationManager::class, ['ownerRecord' => $pet, 'pageClass' => ViewPet::class])
        ->filterTable('status', 'overdue')
        ->assertCanSeeTableRecords([$vencida])
        ->assertCanNotSeeTableRecords([$alDia]);
});
