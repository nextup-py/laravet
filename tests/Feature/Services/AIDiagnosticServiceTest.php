<?php

use App\Models\Pet;
use App\Services\AIDiagnosticService;
use Illuminate\Support\Facades\Http;

it('retorna diagnóstico, tratamiento, urgencia y tokens cuando la API responde correctamente', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => json_encode(['diagnosis' => 'Gastroenteritis', 'treatment' => 'Dieta blanda', 'urgency' => 'media'])],
            ],
            'usage' => ['input_tokens' => 123, 'output_tokens' => 45],
        ]),
    ]);

    $pet = Pet::factory()->create();

    $result = app(AIDiagnosticService::class)->suggest($pet, 'Vómitos y diarrea desde ayer.');

    expect($result)->toBe([
        'diagnosis' => 'Gastroenteritis',
        'treatment' => 'Dieta blanda',
        'urgency' => 'media',
        'input_tokens' => 123,
        'output_tokens' => 45,
    ]);
});

it('no lanza excepción si la urgencia viene ausente o inválida, y la devuelve como null', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => json_encode(['diagnosis' => 'Gastroenteritis', 'treatment' => 'Dieta blanda', 'urgency' => 'no-existe'])],
            ],
        ]),
    ]);

    $pet = Pet::factory()->create();

    $result = app(AIDiagnosticService::class)->suggest($pet, 'Vómitos y diarrea desde ayer.');

    expect($result['urgency'])->toBeNull()
        ->and($result['input_tokens'])->toBeNull()
        ->and($result['output_tokens'])->toBeNull();
});

it('lanza una excepción si la API responde con error', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response(null, 500),
    ]);

    $pet = Pet::factory()->create();

    app(AIDiagnosticService::class)->suggest($pet, 'Anamnesis de prueba');
})->throws(RuntimeException::class);

it('lanza una excepción si la respuesta no tiene el formato JSON esperado', function () {
    config(['services.anthropic.key' => 'test-key']);

    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [
                ['text' => 'esto no es JSON válido'],
            ],
        ]),
    ]);

    $pet = Pet::factory()->create();

    app(AIDiagnosticService::class)->suggest($pet, 'Anamnesis de prueba');
})->throws(RuntimeException::class);

it('lanza una excepción sin llamar a la API si falta la API key', function () {
    config(['services.anthropic.key' => null]);

    Http::fake();

    $pet = Pet::factory()->create();

    try {
        app(AIDiagnosticService::class)->suggest($pet, 'Anamnesis de prueba');
    } catch (RuntimeException) {
        // esperado
    }

    Http::assertNothingSent();
});

it('envía a la IA el peso y la temperatura medidos en la consulta, con formato de coma', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode(['diagnosis' => 'X', 'treatment' => 'Y', 'urgency' => 'baja'])]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);
    $pet = Pet::factory()->create(['weight' => 10]);

    app(AIDiagnosticService::class)->suggest($pet, 'Decaimiento.', 12.5, 39.4);

    Http::assertSent(fn ($request): bool => str_contains($request['messages'][0]['content'], 'Peso: 12,5 kg (medido en esta consulta)')
        && str_contains($request['messages'][0]['content'], 'Temperatura: 39,4 °C (medida en esta consulta)'));
});

it('sin signos vitales de la consulta usa el peso actual de la mascota', function () {
    config(['services.anthropic.key' => 'test-key']);
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => json_encode(['diagnosis' => 'X', 'treatment' => 'Y', 'urgency' => 'baja'])]],
            'usage' => ['input_tokens' => 1, 'output_tokens' => 1],
        ]),
    ]);
    $pet = Pet::factory()->create(['weight' => 10]);

    app(AIDiagnosticService::class)->suggest($pet, 'Decaimiento.');

    Http::assertSent(fn ($request): bool => str_contains($request['messages'][0]['content'], 'Peso: 10 kg')
        && ! str_contains($request['messages'][0]['content'], 'Temperatura'));
});
