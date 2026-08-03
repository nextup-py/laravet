<?php

namespace App\Models;

use App\Enums\AiUrgencyLevel;
use App\Enums\AiUsageStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Consultation extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     * Estos campos se pueden rellenar directamente al crear o actualizar una consulta.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'anamnesis', // Historia clínica del paciente
        'diagnosis', // Diagnóstico del paciente
        'treatment', // Tratamiento prescrito
        'observation', // Observaciones adicionales
        'consultation_date', // Fecha en la que ocurrió la consulta
        'pet_id', // ID de la mascota asociada a la consulta
        'user_id', // ID del usuario (veterinario) que creó la consulta
        'ai_diagnosis_suggestion', // Diagnóstico crudo sugerido por la IA (para auditoría)
        'ai_treatment_suggestion', // Tratamiento crudo sugerido por la IA (para auditoría)
        'ai_urgency', // Nivel de urgencia detectado por la IA
        'ai_suggested_at', // Cuándo se generó la sugerencia de IA
        'ai_input_tokens', // Tokens de entrada consumidos por la sugerencia
        'ai_output_tokens', // Tokens de salida consumidos por la sugerencia
    ];

    protected function casts(): array
    {
        return [
            'consultation_date' => 'date',
            'ai_urgency' => AiUrgencyLevel::class,
            'ai_suggested_at' => 'datetime',
        ];
    }

    /**
     * Indica si el diagnóstico/tratamiento final es manual, una sugerencia de IA usada
     * tal cual, o una sugerencia de IA que el veterinario editó antes de guardar.
     */
    public function aiUsageStatus(): AiUsageStatus
    {
        if (is_null($this->ai_suggested_at)) {
            return AiUsageStatus::Manual;
        }

        if ($this->diagnosis === $this->ai_diagnosis_suggestion && $this->treatment === $this->ai_treatment_suggestion) {
            return AiUsageStatus::UsedAsIs;
        }

        return AiUsageStatus::Edited;
    }

    /**
     * Una consulta pertenece a una mascota.
     * Esta relación define que cada consulta está asociada con una mascota específica.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Una consulta pertenece a un usuario (veterinario).
     * Esta relación define que cada consulta está asociada con un veterinario específico.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
