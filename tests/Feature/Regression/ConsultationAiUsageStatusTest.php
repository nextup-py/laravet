<?php

use App\Enums\AiUsageStatus;
use App\Models\Consultation;

it('retorna Manual si la consulta nunca usó sugerencia de IA', function () {
    $consultation = Consultation::factory()->create([
        'ai_suggested_at' => null,
        'ai_diagnosis_suggestion' => null,
        'ai_treatment_suggestion' => null,
    ]);

    expect($consultation->aiUsageStatus())->toBe(AiUsageStatus::Manual);
});

it('retorna UsedAsIs si el diagnóstico y tratamiento final coinciden con la sugerencia de la IA', function () {
    $consultation = Consultation::factory()->create([
        'diagnosis' => 'Gastroenteritis',
        'treatment' => 'Dieta blanda',
        'ai_diagnosis_suggestion' => 'Gastroenteritis',
        'ai_treatment_suggestion' => 'Dieta blanda',
        'ai_suggested_at' => now(),
    ]);

    expect($consultation->aiUsageStatus())->toBe(AiUsageStatus::UsedAsIs);
});

it('retorna Edited si el veterinario modificó el diagnóstico o tratamiento sugerido por la IA', function () {
    $consultation = Consultation::factory()->create([
        'diagnosis' => 'Gastroenteritis aguda confirmada por palpación',
        'treatment' => 'Dieta blanda',
        'ai_diagnosis_suggestion' => 'Gastroenteritis',
        'ai_treatment_suggestion' => 'Dieta blanda',
        'ai_suggested_at' => now(),
    ]);

    expect($consultation->aiUsageStatus())->toBe(AiUsageStatus::Edited);
});
