<?php

namespace App\Services;

use App\Models\Pet;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AIDiagnosticService
{
    public function suggest(Pet $pet, string $anamnesis): array
    {
        $age = $this->formatAge($pet->birthdate);
        $userPrompt = "Paciente: {$pet->species} | Raza: {$pet->breed} | Edad: {$age} | Peso: {$pet->weight}kg\n"
            . "Género: {$pet->gender} | Reproducción: {$pet->reproduction}\n\n"
            . "Anamnesis:\n{$anamnesis}";

        $response = Http::withHeaders([
            'x-api-key' => config('services.anthropic.key'),
            'anthropic-version' => '2023-06-01',
        ])->post('https://api.anthropic.com/v1/messages', [
            'model' => 'claude-opus-4-8',
            'max_tokens' => 4096,
            'system' => 'Eres un veterinario clínico experto. A partir de los datos del paciente y la anamnesis proporcionada, generá un diagnóstico presuntivo y un plan de tratamiento. Respondé ÚNICAMENTE con JSON válido con las claves "diagnosis" y "treatment", sin bloques de código Markdown ni texto adicional antes o después del JSON.',
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Error al conectar con la API de IA: ' . $response->status());
        }

        $content = $response->json('content.0.text') ?? '';
        // Por si el modelo igual envuelve la respuesta en un bloque ```json ... ``` a pesar de la instrucción
        $content = trim(preg_replace('/^```(?:json)?\s*|\s*```$/m', '', $content));

        $result = json_decode($content, true);

        if (!isset($result['diagnosis'], $result['treatment'])) {
            throw new RuntimeException('La respuesta de la IA no tiene el formato esperado.');
        }

        return $result;
    }

    private function formatAge(mixed $birthdate): string
    {
        $birth = Carbon::parse($birthdate);
        $years = $birth->diffInYears(now());

        if ($years > 0) {
            return "{$years} " . ($years === 1 ? 'año' : 'años');
        }

        $months = $birth->diffInMonths(now());
        return "{$months} " . ($months === 1 ? 'mes' : 'meses');
    }
}
