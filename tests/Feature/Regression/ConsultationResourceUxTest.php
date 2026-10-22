<?php

use App\Enums\PetSpecies;
use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\ConsultationResource\Pages\CreateConsultation;
use App\Filament\Resources\ConsultationResource\Pages\ListConsultations;
use App\Filament\Resources\ConsultationResource\Pages\ViewConsultation;
use App\Models\Consultation;
use App\Models\Pet;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

it('filtra consultas por especie de la mascota', function () {
    actingAsRole('admin');
    $perro = Consultation::factory()->for(Pet::factory()->state(['species' => PetSpecies::Canino]))->create();
    $gato = Consultation::factory()->for(Pet::factory()->state(['species' => PetSpecies::Felino]))->create();

    Livewire::test(ListConsultations::class)
        ->filterTable('species', [PetSpecies::Canino->value])
        ->assertCanSeeTableRecords([$perro])
        ->assertCanNotSeeTableRecords([$gato]);
});

it('filtra consultas por urgencia de la IA', function () {
    actingAsRole('admin');
    $alta = Consultation::factory()->create(['ai_urgency' => 'alta', 'ai_suggested_at' => now()]);
    $baja = Consultation::factory()->create(['ai_urgency' => 'baja', 'ai_suggested_at' => now()]);

    Livewire::test(ListConsultations::class)
        ->filterTable('ai_urgency', ['alta'])
        ->assertCanSeeTableRecords([$alta])
        ->assertCanNotSeeTableRecords([$baja]);
});

it('filtra consultas por uso de la IA: manual, tal cual y editado', function () {
    actingAsRole('admin');
    $manual = Consultation::factory()->create();
    $tal = Consultation::factory()->create([
        'ai_suggested_at' => now(),
        'ai_diagnosis_suggestion' => 'D',
        'ai_treatment_suggestion' => 'T',
        'diagnosis' => 'D',
        'treatment' => 'T',
    ]);
    $editada = Consultation::factory()->create([
        'ai_suggested_at' => now(),
        'ai_diagnosis_suggestion' => 'D',
        'ai_treatment_suggestion' => 'T',
        'diagnosis' => 'D modificado',
        'treatment' => 'T',
    ]);

    Livewire::test(ListConsultations::class)
        ->filterTable('ai_usage', 'Manual')
        ->assertCanSeeTableRecords([$manual])
        ->assertCanNotSeeTableRecords([$tal, $editada]);

    Livewire::test(ListConsultations::class)
        ->filterTable('ai_usage', 'UsedAsIs')
        ->assertCanSeeTableRecords([$tal])
        ->assertCanNotSeeTableRecords([$manual, $editada]);

    Livewire::test(ListConsultations::class)
        ->filterTable('ai_usage', 'Edited')
        ->assertCanSeeTableRecords([$editada])
        ->assertCanNotSeeTableRecords([$manual, $tal]);
});

it('filtra consultas por veterinario', function () {
    actingAsRole('admin');
    $vet = User::factory()->create();
    $suya = Consultation::factory()->create(['user_id' => $vet->id]);
    $ajena = Consultation::factory()->create();

    Livewire::test(ListConsultations::class)
        ->filterTable('user_id', $vet->id)
        ->assertCanSeeTableRecords([$suya])
        ->assertCanNotSeeTableRecords([$ajena]);
});

it('la tabla muestra al propietario bajo el nombre de la mascota', function () {
    actingAsRole('admin');
    $consulta = Consultation::factory()->create();

    Livewire::test(ListConsultations::class)
        ->assertSee($consulta->pet->owner->full_name);
});

it('el formulario muestra el contexto de la mascota con sus últimas consultas', function () {
    actingAsRole('admin');
    $pet = Pet::factory()->create(['breed' => 'Labrador']);
    Consultation::factory()->create(['pet_id' => $pet->id, 'diagnosis' => 'Otitis externa previa']);

    Livewire::test(CreateConsultation::class)
        ->fillForm(['pet_id' => $pet->id])
        ->assertSee('Labrador')
        ->assertSee('Otitis externa previa');
});

it('la ficha usa mascota y fecha como título', function () {
    actingAsRole('admin');
    $consulta = Consultation::factory()->create();

    Livewire::test(ViewConsultation::class, ['record' => $consulta->getRouteKey()])
        ->assertSee("Consulta de {$consulta->pet->name}")
        ->assertSee($consulta->consultation_date->format('d/m/Y'));
});

it('la búsqueda global de consultas muestra mascota y diagnóstico', function () {
    $consulta = Consultation::factory()->create();

    expect(ConsultationResource::getGlobalSearchResultTitle($consulta))->toContain($consulta->pet->name)
        ->and(ConsultationResource::getGloballySearchableAttributes())->toContain('diagnosis', 'pet.name');
});

it('la ficha de consulta no enlaza a la mascota si el usuario no puede verla', function () {
    $role = Role::create(['name' => 'solo-consultas', 'guard_name' => 'web']);
    $role->givePermissionTo(['view_any_consultation', 'view_consultation']);
    $user = User::factory()->create();
    $user->assignRole($role);
    test()->actingAs($user);

    $consulta = Consultation::factory()->create();

    Livewire::test(ViewConsultation::class, ['record' => $consulta->getRouteKey()])
        ->assertSee($consulta->pet->name)
        ->assertDontSee(route('filament.admin.resources.pets.view', ['record' => $consulta->pet_id]));
});
