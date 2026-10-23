<?php

namespace App\Models;

use App\Enums\VaccinationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
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

    /**
     * Estado de la vacunación según la fecha de próxima aplicación.
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: function (): VaccinationStatus {
            $next = Carbon::parse($this->next_application)->startOfDay();

            return match (true) {
                $next->lt(today()) => VaccinationStatus::Overdue,
                $next->lte(today()->addDays(VaccinationStatus::DUE_SOON_DAYS)) => VaccinationStatus::DueSoon,
                default => VaccinationStatus::UpToDate,
            };
        });
    }

    public function scopeOverdue(Builder $query): Builder
    {
        return $query->where('next_application', '<', today()->toDateString());
    }

    public function scopeDueSoon(Builder $query): Builder
    {
        return $query->whereBetween('next_application', [
            today()->toDateString(),
            today()->addDays(VaccinationStatus::DUE_SOON_DAYS)->endOfDay()->toDateTimeString(),
        ]);
    }

    public function scopeUpToDate(Builder $query): Builder
    {
        return $query->where('next_application', '>', today()->addDays(VaccinationStatus::DUE_SOON_DAYS)->endOfDay()->toDateTimeString());
    }

    public function scopeWithStatus(Builder $query, VaccinationStatus $status): Builder
    {
        return match ($status) {
            VaccinationStatus::Overdue => $query->overdue(),
            VaccinationStatus::DueSoon => $query->dueSoon(),
            VaccinationStatus::UpToDate => $query->upToDate(),
        };
    }
}
