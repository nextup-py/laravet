<?php

use App\Filament\Resources\ConsultationResource\Pages\ListConsultations;
use App\Filament\Resources\ConsultationResource\Pages\ViewConsultation;
use App\Filament\Resources\PetResource;
use App\Filament\Resources\UserResource;
use App\Models\Consultation;
use App\Models\Pet;
use Livewire\Livewire;

it('ordena la tabla de consultas por fecha de consulta más reciente primero', function () {
    actingAsRole('veterinarian');
    $antigua = Consultation::factory()->create(['consultation_date' => '2026-01-01']);
    $reciente = Consultation::factory()->create(['consultation_date' => '2026-06-01']);

    $records = Livewire::test(ListConsultations::class)
        ->instance()
        ->getTable()
        ->getRecords();

    expect($records->first()->id)->toBe($reciente->id)
        ->and($records->last()->id)->toBe($antigua->id);
});

it('la columna de veterinario en la tabla top-level tiene el label correcto', function () {
    actingAsRole('veterinarian');
    Consultation::factory()->create();

    Livewire::test(ListConsultations::class)
        ->assertSee('Veterinario');
});

it('muestra la urgencia detectada por la IA como badge en la tabla', function () {
    actingAsRole('veterinarian');
    Consultation::factory()->create(['ai_urgency' => 'alta', 'ai_suggested_at' => now()]);

    Livewire::test(ListConsultations::class)
        ->assertSee('Alta');
});

it('preserva los saltos de línea de la anamnesis en la vista de detalle', function () {
    actingAsRole('veterinarian');
    $consultation = Consultation::factory()->create([
        'anamnesis' => "Primer párrafo.\nSegundo párrafo.",
    ]);

    Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
        ->assertSeeHtml('Primer párrafo.<br');
});

it('muestra la fecha de registro y última edición en la vista de detalle', function () {
    actingAsRole('veterinarian');
    $consultation = Consultation::factory()->create();

    Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
        ->assertSee('Registrado el')
        ->assertSee('Última edición');
});

it('el enlace a la mascota está visible para cualquier rol clínico, pero el enlace al veterinario solo para admin', function () {
    $pet = Pet::factory()->create();
    $consultation = Consultation::factory()->create(['pet_id' => $pet->id]);
    $petUrl = PetResource::getUrl('view', ['record' => $pet->id]);
    $userUrl = UserResource::getUrl('view', ['record' => $consultation->user_id]);

    actingAsRole('veterinarian');
    Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
        ->assertSeeHtml($petUrl)
        ->assertDontSeeHtml($userUrl);

    actingAsRole('admin');
    Livewire::test(ViewConsultation::class, ['record' => $consultation->getRouteKey()])
        ->assertSeeHtml($petUrl)
        ->assertSeeHtml($userUrl);
});
