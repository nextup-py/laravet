<?php

use App\Enums\PetSpecies;
use App\Enums\SurgeryStatus;
use App\Filament\Resources\PetResource\Pages\ViewPet;
use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\SurgeryResource\Pages\CreateSurgery;
use App\Filament\Resources\SurgeryResource\Pages\ListSurgeries;
use App\Filament\Resources\SurgeryResource\Pages\ViewSurgery;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\User;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('calcula el estado según la fecha de la cirugía', function () {
    expect(Surgery::factory()->make(['date' => today()->addDay()])->status)->toBe(SurgeryStatus::Scheduled)
        ->and(Surgery::factory()->make(['date' => today()])->status)->toBe(SurgeryStatus::Today)
        ->and(Surgery::factory()->make(['date' => today()->subDay()])->status)->toBe(SurgeryStatus::Done);
});

it('permite programar una cirugía con fecha futura', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(CreateSurgery::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'date' => today()->addDays(3)->toDateString(),
            'type' => 'Castración',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Surgery::where('pet_id', $pet->id)->first()->status)->toBe(SurgeryStatus::Scheduled);
});

it('las cirugías programadas aparecen en el scope upcoming del panel de inicio', function () {
    $futura = Surgery::factory()->create(['date' => today()->addDays(2)]);
    $pasada = Surgery::factory()->create(['date' => today()->subDays(2)]);

    expect(Surgery::upcoming()->pluck('id')->all())->toBe([$futura->id])
        ->and(Surgery::upcoming()->pluck('id')->all())->not->toContain($pasada->id);
});

it('los tipos de cirugía están ordenados alfabéticamente y mantienen clave igual a etiqueta', function () {
    $options = SurgeryResource::typeOptions();
    $keys = array_keys($options);
    $sorted = $keys;
    usort($sorted, fn ($a, $b) => strcmp(strtolower(Str::ascii($a)), strtolower(Str::ascii($b))));

    expect($keys)->toBe($sorted)
        ->and($options)->toBe(array_combine($keys, $keys))
        ->and($keys)->toContain('Sutura de herida', 'Extracción dental', 'Desobstrucción uretral');
});

it('la tabla muestra el propietario, el veterinario y el estado', function () {
    actingAsRole('admin');
    $cirugia = Surgery::factory()->create(['date' => today()->addDay()]);

    Livewire::test(ListSurgeries::class)
        ->assertSee($cirugia->pet->owner->full_name)
        ->assertSee($cirugia->user->name)
        ->assertSee('Programada');
});

it('ordena por fecha descendente por defecto', function () {
    actingAsRole('admin');
    $antigua = Surgery::factory()->create(['date' => today()->subYear()]);
    $reciente = Surgery::factory()->create(['date' => today()->subDay()]);

    Livewire::test(ListSurgeries::class)
        ->assertCanSeeTableRecords([$reciente, $antigua], inOrder: true);
});

it('filtra cirugías por tipo, fechas, veterinario, mascota y especie', function () {
    actingAsRole('admin');
    $vet = User::factory()->create();
    $a = Surgery::factory()->for(Pet::factory()->state(['species' => PetSpecies::Canino]))
        ->create(['type' => 'Castración', 'date' => today()->subDays(30), 'user_id' => $vet->id]);
    $b = Surgery::factory()->for(Pet::factory()->state(['species' => PetSpecies::Felino]))
        ->create(['type' => 'Cesárea', 'date' => today()->subDays(2)]);

    Livewire::test(ListSurgeries::class)
        ->filterTable('type', ['Castración'])
        ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);

    Livewire::test(ListSurgeries::class)
        ->filterTable('date', ['from' => today()->subDays(5)->toDateString(), 'until' => null])
        ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);

    Livewire::test(ListSurgeries::class)
        ->filterTable('user_id', $vet->id)
        ->assertCanSeeTableRecords([$a])->assertCanNotSeeTableRecords([$b]);

    Livewire::test(ListSurgeries::class)
        ->filterTable('pet_id', $b->pet_id)
        ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);

    Livewire::test(ListSurgeries::class)
        ->filterTable('species', [PetSpecies::Felino->value])
        ->assertCanSeeTableRecords([$b])->assertCanNotSeeTableRecords([$a]);
});

it('el formulario muestra el contexto de la mascota con sus cirugías previas', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['breed' => 'Labrador']);
    Surgery::factory()->create(['pet_id' => $pet->id, 'type' => 'Cesárea']);

    Livewire::test(CreateSurgery::class)
        ->fillForm(['pet_id' => $pet->id])
        ->assertSee('Labrador')
        ->assertSee('Cesárea');
});

it('registrar cirugía desde la ficha de mascota precarga la mascota', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create();

    Livewire::test(ViewPet::class, ['record' => $pet->getRouteKey()])
        ->assertActionVisible('registerSurgery');

    Livewire::withQueryParams(['pet_id' => $pet->id])
        ->test(CreateSurgery::class)
        ->assertFormSet(['pet_id' => (string) $pet->id]);
});

it('la ficha usa tipo y mascota como título y muestra veterinario y estado', function () {
    actingAsRole('admin');
    $cirugia = Surgery::factory()->create(['date' => today()->subDay()]);

    Livewire::test(ViewSurgery::class, ['record' => $cirugia->getRouteKey()])
        ->assertSee("{$cirugia->type} · {$cirugia->pet->name}")
        ->assertSee($cirugia->user->name)
        ->assertSee('Realizada');
});

it('la ficha no enlaza a la mascota si el usuario no puede verla', function () {
    $role = Role::create(['name' => 'solo-cirugias', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_surgery', 'view_surgery']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    $cirugia = Surgery::factory()->create();

    Livewire::test(ViewSurgery::class, ['record' => $cirugia->getRouteKey()])
        ->assertSee($cirugia->pet->name)
        ->assertDontSee(route('filament.admin.resources.pets.view', ['record' => $cirugia->pet_id]));
});

it('la búsqueda global de cirugías incluye mascota, fecha y estado', function () {
    $cirugia = Surgery::factory()->create(['date' => today()->subDay()]);

    expect(SurgeryResource::getGloballySearchableAttributes())->toContain('type', 'observation', 'pet.name')
        ->and(SurgeryResource::getGlobalSearchResultTitle($cirugia))->toContain($cirugia->pet->name)
        ->and(SurgeryResource::getGlobalSearchResultDetails($cirugia)['Estado'])->toBe('Realizada');
});
