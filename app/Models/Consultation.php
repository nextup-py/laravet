<?php

namespace App\Models;

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
    ];

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
