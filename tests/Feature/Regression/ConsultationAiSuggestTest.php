<?php

use App\Filament\Resources\ConsultationResource\Pages\CreateConsultation;
use App\Filament\Resources\PetResource\Pages\EditPet;
use App\Filament\Resources\PetResource\RelationManagers\ConsultationsRelationManager;
use App\Models\Pet;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    config(['services.anthropic.key' => 'test-key']);
});

function fakeAiResponse(string $diagnosis = 'Diagnóstico IA', string $treatment = 'Tratamiento IA', string $urgency = 'baja'): void
{
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => json_encode(['diagnosis' => $diagnosis, 'treatment' => $treatment, 'urgency' => $urgency])],
            ],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 50],
        ]),
    ]);
}

it('el botón IA completa diagnóstico y tratamiento en el form top-level', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    fakeAiResponse();

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Anamnesis de prueba',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertFormSet([
            'diagnosis' => 'Diagnóstico IA',
            'treatment' => 'Tratamiento IA',
            'ai_diagnosis_suggestion' => 'Diagnóstico IA',
            'ai_treatment_suggestion' => 'Tratamiento IA',
            'ai_urgency' => 'baja',
            'ai_input_tokens' => 100,
            'ai_output_tokens' => 50,
        ]);
});

it('el botón IA muestra una alerta de urgencia si la IA detecta una urgencia alta', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    fakeAiResponse(urgency: 'alta');

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Convulsiones y dificultad respiratoria',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertNotified('Posible urgencia detectada');
});

it('el botón IA pide confirmación en el form top-level si ya hay diagnóstico o tratamiento cargado', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    fakeAiResponse();

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Anamnesis de prueba',
            'diagnosis' => 'Diagnóstico escrito a mano',
        ])
        ->mountFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertFormComponentActionMounted('aiSuggestAction', 'aiSuggest');
});

it('el botón IA muestra notificación de error si la API falla en el form top-level', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();

    Http::fake([
        'api.anthropic.com/*' => Http::response(null, 500),
    ]);

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Anamnesis de prueba',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertNotified('Error al generar sugerencia');
});

it('el botón IA completa diagnóstico y tratamiento dentro de la ficha de la mascota', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    fakeAiResponse();

    // pageClass debe ser EditPet, no ViewPet: Filament vuelve las RelationManagers de
    // solo lectura en páginas ViewRecord por defecto, lo que deshabilitaría la action 'create'.
    Livewire::test(ConsultationsRelationManager::class, [
        'ownerRecord' => $pet,
        'pageClass' => EditPet::class,
    ])
        ->mountTableAction('create')
        ->setTableActionData(['anamnesis' => 'Anamnesis de prueba'])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest', formName: 'mountedTableActionForm')
        ->assertFormSet([
            'diagnosis' => 'Diagnóstico IA',
            'treatment' => 'Tratamiento IA',
        ], 'mountedTableActionForm');
});
