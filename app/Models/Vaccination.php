<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Vaccination extends Model
{
    use HasFactory;

    /**
     * Los atributos que se pueden asignar masivamente.
     * Estos campos se pueden rellenar directamente al crear o actualizar una consulta.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'vaccine', // Nombre de la vacuna aplicada
        'application_date', // Fecha de aplicación de la vacuna
        'next_application', // Fecha de la próxima aplicación de la vacuna
        'batch', // Número de lote de la vacuna
        'manufacturer', // Fabricante de la vacuna
        'observation', // Observaciones adicionales sobre la vacunación
        'pet_id', // ID de la mascota a la que se aplicó la vacuna
        'user_id', // ID del veterinario que aplicó la vacuna
    ];

    /**
     * Una vacunación pertenece a una mascota.
     */
    public function pet(): BelongsTo
    {
        return $this->belongsTo(Pet::class);
    }

    /**
     * Una vacunación pertenece a un usuario (veterinario).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Vacunas vencidas o que vencen dentro de los próximos $days días.
     */
    public function scopeUpcomingOrOverdue(Builder $query, int $days = 7): Builder
    {
        return $query->where('next_application', '<', now()->addDays($days));
    }
}
