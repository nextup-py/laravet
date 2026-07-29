<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Test extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     * Estos campos se pueden rellenar directamente al crear o actualizar un examen.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'date', // Fecha del examen
        'type', // Tipo de examen realizado
        'result', // Resultado del examen
        'observation', // Observaciones adicionales sobre el examen
        'pet_id', // ID de la mascota a la que se le realizó el examen
        'user_id', // ID del veterinario que realizó el examen
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
        ];
    }

    /**
     * Un examen pertenece a una mascota.
     * Esta relación define que cada examen está asociado con una mascota específica.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Un examen pertenece a un usuario (veterinario).
     * Esta relación define que cada examen está asociado con un veterinario específico.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
