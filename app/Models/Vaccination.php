<?php

namespace App\Models;

use App\Enums\VaccinationStatus;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

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
     * Subconsulta: existe una aplicación más reciente de la misma vacuna para la misma mascota.
     * "La misma vacuna" es el mismo nombre exacto; ante igual fecha de aplicación gana el id mayor.
     */
    private function newerApplicationExists(QueryBuilder $query): QueryBuilder
    {
        return $query->select(DB::raw(1))
            ->from('vaccinations as newer')
            ->whereColumn('newer.pet_id', 'vaccinations.pet_id')
            ->whereColumn('newer.vaccine', 'vaccinations.vaccine')
            ->where(fn (QueryBuilder $w) => $w
                ->whereColumn('newer.application_date', '>', 'vaccinations.application_date')
                ->orWhere(fn (QueryBuilder $tie) => $tie
                    ->whereColumn('newer.application_date', 'vaccinations.application_date')
                    ->whereColumn('newer.id', '>', 'vaccinations.id')));
    }

    /**
     * Solo la última aplicación de cada vacuna por mascota (las vigentes).
     */
    public function scopeCurrent(Builder $query): Builder
    {
        return $query->whereNotExists(fn ($sub) => $this->newerApplicationExists($sub));
    }

    /**
     * Aplicaciones ya reemplazadas por una más reciente de la misma vacuna.
     */
    public function scopeRenewed(Builder $query): Builder
    {
        return $query->whereExists(fn ($sub) => $this->newerApplicationExists($sub));
    }

    /**
     * Agrega `is_renewed` a cada fila para calcular el estado sin una consulta extra por registro.
     */
    public function scopeWithRenewedFlag(Builder $query): Builder
    {
        return $query->addSelect([
            'vaccinations.*',
            'is_renewed' => fn ($sub) => $this->newerApplicationExists($sub),
        ]);
    }

    /**
     * Vigentes que requieren atención: vencidas o que vencen dentro de los próximos $days días.
     */
    public function scopeActionable(Builder $query, int $days = 7): Builder
    {
        return $query->current()->upcomingOrOverdue($days);
    }

    /**
     * Estado de la vacunación: renovada si hay una aplicación más reciente de la misma vacuna;
     * si no, según la fecha de próxima aplicación.
     */
    protected function status(): Attribute
    {
        return Attribute::make(get: function (): VaccinationStatus {
            if ($this->isRenewed()) {
                return VaccinationStatus::Renewed;
            }

            $next = Carbon::parse($this->next_application)->startOfDay();

            return match (true) {
                $next->lt(today()) => VaccinationStatus::Overdue,
                $next->lte(today()->addDays(VaccinationStatus::DUE_SOON_DAYS)) => VaccinationStatus::DueSoon,
                default => VaccinationStatus::UpToDate,
            };
        });
    }

    /**
     * Usa `is_renewed` si se cargó con `withRenewedFlag()`; si no, consulta la base.
     */
    public function isRenewed(): bool
    {
        if (array_key_exists('is_renewed', $this->attributes)) {
            return (bool) $this->attributes['is_renewed'];
        }

        return $this->exists && static::query()->renewed()->whereKey($this->getKey())->exists();
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
            VaccinationStatus::Overdue => $query->current()->overdue(),
            VaccinationStatus::DueSoon => $query->current()->dueSoon(),
            VaccinationStatus::UpToDate => $query->current()->upToDate(),
            VaccinationStatus::Renewed => $query->renewed(),
        };
    }
}
