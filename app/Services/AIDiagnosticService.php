<?php

namespace App\Services;

use App\Enums\AiUrgencyLevel;
use App\Models\Pet;
use App\Support\Measure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class AIDiagnosticService
{
    /**
     * @param  float|string|null  $weight  Peso medido en esta consulta (kg); sin él se usa el peso actual de la mascota.
     * @param  float|string|null  $temperature  Temperatura medida en esta consulta (°C).
     */
    public function suggest(Pet $pet, string $anamnesis, float|string|null $weight = null, float|string|null $temperature = null): array
    {
        if (blank(config('services.anthropic.key'))) {
            throw new RuntimeException('ANTHROPIC_API_KEY no configurada.');
        }

        $species = $pet->species->getLabel();
        $gender = $pet->gender->getLabel();
        $reproduction = $pet->reproduction->getLabel();
        $weightLine = is_numeric($weight)
            ? 'Peso: '.Measure::number($weight).' kg (medido en esta consulta)'
            : 'Peso: '.Measure::number($pet->weight).' kg';
        $temperatureLine = is_numeric($temperature) ? ' | Temperatura: '.Measure::number($temperature, 1).' °C (medida en esta consulta)' : '';
        $userPrompt = "Paciente: {$species} | Raza: {$pet->breed} | Edad: {$pet->age} | {$weightLine}{$temperatureLine}\n"
            ."Género: {$gender} | Reproducción: {$reproduction}\n\n"
            ."Anamnesis:\n{$anamnesis}";

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->timeout(30)->retry(2, 500, throw: false)->post('https://api.anthropic.com/v1/messages', [
            'model' => config('services.anthropic.model'),
            'max_tokens' => 4096,
            'system' => 'Eres un veterinario clínico experto asistiendo a otro veterinario, no al dueño de la mascota. A partir de los datos del paciente y la anamnesis proporcionada, genera un diagnóstico presuntivo, un plan de tratamiento, y un nivel de urgencia. El diagnóstico es una sugerencia que el veterinario tratante debe confirmar con su propio criterio clínico antes de aplicarla. Si los síntomas descriptos sugieren una condición que pone en riesgo la vida del paciente o requiere atención inmediata, marca la urgencia como "alta" o "emergencia" y menciónalo explícitamente en el diagnóstico. Responde ÚNICAMENTE con JSON válido con las claves "diagnosis", "treatment" y "urgency" (uno de: "baja", "media", "alta", "emergencia"), sin bloques de código Markdown ni texto adicional antes o después del JSON.',
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Error al conectar con la API de IA: '.$response->status());
        }

        $content = $response->json('content.0.text') ?? '';
        // Por si el modelo igual envuelve la respuesta en un bloque ```json ... ``` a pesar de la instrucción
        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content));

        $result = json_decode($content, true);

        if (! isset($result['diagnosis'], $result['treatment'])) {
            throw new RuntimeException('La respuesta de la IA no tiene el formato esperado.');
        }

        $urgency = AiUrgencyLevel::tryFrom($result['urgency'] ?? '');

        if (is_null($urgency)) {
            Log::warning('La IA no devolvió un nivel de urgencia válido.', ['urgency' => $result['urgency'] ?? null]);
        }

        return [
            'diagnosis' => $result['diagnosis'],
            'treatment' => $result['treatment'],
            'urgency' => $urgency?->value,
            'input_tokens' => $response->json('usage.input_tokens'),
            'output_tokens' => $response->json('usage.output_tokens'),
        ];
    }
}
