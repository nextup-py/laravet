<?php

use App\Filament\Resources\ConsultationResource\Pages\CreateConsultation;
use App\Filament\Resources\PetResource\Pages\EditPet;
use App\Filament\Resources\PetResource\RelationManagers\ConsultationsRelationManager;
use App\Models\Pet;
use Filament\Notifications\Notification;
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

it('el botón IA pide completar la anamnesis primero si está vacía en el form top-level', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();

    Http::fake();

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => '',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertNotified('Completá la anamnesis primero');

    Http::assertNothingSent();
});

it('el botón IA pide completar la anamnesis primero si está vacía dentro de la ficha de la mascota', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();

    Http::fake();

    Livewire::test(ConsultationsRelationManager::class, [
        'ownerRecord' => $pet,
        'pageClass' => EditPet::class,
    ])
        ->mountTableAction('create')
        ->setTableActionData(['anamnesis' => ''])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest', formName: 'mountedTableActionForm')
        ->assertNotified('Completá la anamnesis primero');

    Http::assertNothingSent();
});

it('el form top-level muestra el texto de ayuda y el indicador de "generando" para el botón IA', function () {
    actingAsRole('veterinarian');

    Livewire::test(CreateConsultation::class)
        ->assertSee('Revisá siempre la sugerencia antes de guardar')
        ->assertSeeHtml('Generando sugerencia con IA');
});

it('el form dentro de la ficha de la mascota muestra el texto de ayuda y el indicador de "generando" para el botón IA', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();

    Livewire::test(ConsultationsRelationManager::class, [
        'ownerRecord' => $pet,
        'pageClass' => EditPet::class,
    ])
        ->mountTableAction('create')
        ->assertSee('Revisá siempre la sugerencia antes de guardar')
        ->assertSeeHtml('Generando sugerencia con IA');
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

it('marca el diagnóstico y tratamiento como editados cuando difieren de la sugerencia de la IA en el form top-level', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    fakeAiResponse();

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Anamnesis de prueba',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertSee('Máximo 5000 caracteres.')
        ->set('data.diagnosis', 'Diagnóstico IA modificado por el veterinario')
        ->assertSee('Editado respecto a la sugerencia de la IA');
});

it('no marca el diagnóstico como editado si coincide con la sugerencia de la IA', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    fakeAiResponse();

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Anamnesis de prueba',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertDontSee('Editado respecto a la sugerencia de la IA');
});

it('muestra el badge de urgencia en el form top-level tras generar la sugerencia', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    fakeAiResponse(urgency: 'alta');

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Anamnesis de prueba',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertSee('Urgencia sugerida por la IA: Alta');
});

it('no muestra el badge de urgencia en el form top-level antes de generar sugerencia', function () {
    actingAsRole('veterinarian');

    Livewire::test(CreateConsultation::class)
        ->assertDontSee('Urgencia sugerida por la IA');
});

it('muestra un mensaje específico si la API key de IA no está configurada', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();
    config(['services.anthropic.key' => null]);

    Http::fake();

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Anamnesis de prueba',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertNotified(
            Notification::make()
                ->title('Error al generar sugerencia')
                ->body('La integración con IA no está configurada. Contactá al administrador del sistema.')
                ->danger()
        );

    Http::assertNothingSent();
});

it('muestra un mensaje específico si la respuesta de la IA no tiene el formato esperado', function () {
    actingAsRole('veterinarian');
    $pet = Pet::factory()->create();

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => 'esto no es JSON válido'],
            ],
        ]),
    ]);

    Livewire::test(CreateConsultation::class)
        ->fillForm([
            'pet_id' => $pet->id,
            'anamnesis' => 'Anamnesis de prueba',
        ])
        ->callFormComponentAction('aiSuggestAction', 'aiSuggest')
        ->assertNotified(
            Notification::make()
                ->title('Error al generar sugerencia')
                ->body('La IA devolvió una respuesta inesperada. Intentá nuevamente.')
                ->danger()
        );
});

it('muestra un mensaje específico si falla la conexión con la API de IA', function () {
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
        ->assertNotified(
            Notification::make()
                ->title('Error al generar sugerencia')
                ->body('No se pudo conectar con el servicio de IA. Verificá tu conexión e intentá nuevamente.')
                ->danger()
        );
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
