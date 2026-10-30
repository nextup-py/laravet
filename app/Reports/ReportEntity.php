<?php

namespace App\Reports;

use App\Models\Consultation;
use App\Models\Pet;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Registros sobre los que el usuario arma un reporte personalizado: elige el tipo,
 * el rango de fechas, los filtros y las columnas. Cada caso define sus columnas;
 * las que muestran datos del propietario se marcan (`owner`) y se descartan si el
 * usuario no puede ver propietarios.
 */
enum ReportEntity: string
{
    case Consultations = 'consultations';
    case Vaccinations = 'vaccinations';
    case Surgeries = 'surgeries';
    case Tests = 'tests';
    case Pets = 'pets';

    public function label(): string
    {
        return match ($this) {
            self::Consultations => 'Consultas',
            self::Vaccinations => 'Vacunaciones',
            self::Surgeries => 'Cirugías',
            self::Tests => 'Pruebas laboratoriales',
            self::Pets => 'Mascotas',
        };
    }

    public function dateLabel(): string
    {
        return $this === self::Pets ? 'fecha de registro' : 'fecha';
    }

    public function permission(): string
    {
        return match ($this) {
            self::Consultations => 'view_any_consultation',
            self::Vaccinations => 'view_any_vaccination',
            self::Surgeries => 'view_any_surgery',
            self::Tests => 'view_any_test',
            self::Pets => 'view_any_pet',
        };
    }

    /**
     * @return class-string<Model>
     */
    private function model(): string
    {
        return match ($this) {
            self::Consultations => Consultation::class,
            self::Vaccinations => Vaccination::class,
            self::Surgeries => Surgery::class,
            self::Tests => Test::class,
            self::Pets => Pet::class,
        };
    }

    private function dateColumn(): string
    {
        return match ($this) {
            self::Consultations => 'consultation_date',
            self::Vaccinations => 'application_date',
            self::Surgeries, self::Tests => 'date',
            self::Pets => 'created_at',
        };
    }

    /**
     * El veterinario ve en consultas, cirugías y pruebas solo lo suyo (como en el dashboard).
     */
    private function isScopedToOwnRecords(): bool
    {
        return in_array($this, [self::Consultations, self::Surgeries, self::Tests], true);
    }

    /**
     * Columnas disponibles: clave => etiqueta, con las que traen datos del propietario marcadas.
     *
     * @return array<string, array{label: string, owner: bool, value: \Closure(Model): mixed}>
     */
    public function columns(): array
    {
        $date = fn (string $attribute): \Closure => fn (Model $record): ?string => filled($record->{$attribute}) ? Carbon::parse($record->{$attribute})->format('d/m/Y') : null;
        $pet = fn (Model $record): ?Pet => $this === self::Pets ? $record : $record->pet;

        $common = [
            'pet' => ['label' => 'Mascota', 'owner' => false, 'value' => fn (Model $r): ?string => $pet($r)?->name],
            'species' => ['label' => 'Especie', 'owner' => false, 'value' => fn (Model $r): ?string => $pet($r)?->species?->getLabel()],
            'owner' => ['label' => 'Propietario', 'owner' => true, 'value' => fn (Model $r): ?string => $pet($r)?->owner?->full_name],
            'owner_phone' => ['label' => 'Teléfono del propietario', 'owner' => true, 'value' => fn (Model $r): ?string => $pet($r)?->owner?->phone],
            'vet' => ['label' => 'Veterinario', 'owner' => false, 'value' => fn (Model $r): ?string => $r->user?->name],
        ];

        return match ($this) {
            self::Consultations => [
                'date' => ['label' => 'Fecha', 'owner' => false, 'value' => $date('consultation_date')],
                ...$common,
                'weight' => ['label' => 'Peso (kg)', 'owner' => false, 'value' => fn (Model $r): ?string => $r->weight],
                'temperature' => ['label' => 'Temperatura (°C)', 'owner' => false, 'value' => fn (Model $r): ?string => $r->temperature],
                'diagnosis' => ['label' => 'Diagnóstico', 'owner' => false, 'value' => fn (Model $r): ?string => $r->diagnosis],
                'treatment' => ['label' => 'Tratamiento', 'owner' => false, 'value' => fn (Model $r): ?string => $r->treatment],
            ],
            self::Vaccinations => [
                'date' => ['label' => 'Fecha de aplicación', 'owner' => false, 'value' => $date('application_date')],
                'vaccine' => ['label' => 'Vacuna', 'owner' => false, 'value' => fn (Model $r): ?string => $r->vaccine],
                ...$common,
                'manufacturer' => ['label' => 'Fabricante', 'owner' => false, 'value' => fn (Model $r): ?string => $r->manufacturer],
                'batch' => ['label' => 'Lote', 'owner' => false, 'value' => fn (Model $r): ?string => $r->batch],
                'next_application' => ['label' => 'Próxima aplicación', 'owner' => false, 'value' => $date('next_application')],
                'status' => ['label' => 'Estado', 'owner' => false, 'value' => fn (Model $r): string => $r->status->getLabel()],
            ],
            self::Surgeries => [
                'date' => ['label' => 'Fecha', 'owner' => false, 'value' => $date('date')],
                'type' => ['label' => 'Tipo', 'owner' => false, 'value' => fn (Model $r): ?string => $r->type],
                ...$common,
                'status' => ['label' => 'Estado', 'owner' => false, 'value' => fn (Model $r): string => $r->status->getLabel()],
                'observation' => ['label' => 'Observación', 'owner' => false, 'value' => fn (Model $r): ?string => $r->observation],
            ],
            self::Tests => [
                'date' => ['label' => 'Fecha', 'owner' => false, 'value' => $date('date')],
                'type' => ['label' => 'Tipo', 'owner' => false, 'value' => fn (Model $r): ?string => $r->type],
                ...$common,
                'status' => ['label' => 'Estado', 'owner' => false, 'value' => fn (Model $r): string => $r->status->getLabel()],
            ],
            self::Pets => [
                'date' => ['label' => 'Fecha de registro', 'owner' => false, 'value' => $date('created_at')],
                'name' => ['label' => 'Nombre', 'owner' => false, 'value' => fn (Model $r): ?string => $r->name],
                'species' => $common['species'],
                'breed' => ['label' => 'Raza', 'owner' => false, 'value' => fn (Model $r): ?string => $r->breed],
                'gender' => ['label' => 'Género', 'owner' => false, 'value' => fn (Model $r): ?string => $r->gender?->getLabel()],
                'owner' => $common['owner'],
                'owner_phone' => $common['owner_phone'],
                'active' => ['label' => 'Activo', 'owner' => false, 'value' => fn (Model $r): string => $r->active ? 'Sí' : 'No'],
                'vet' => ['label' => 'Registrada por', 'owner' => false, 'value' => fn (Model $r): ?string => $r->user?->name],
            ],
        };
    }

    /**
     * Columnas que el usuario puede elegir: sin las del propietario si no puede ver propietarios.
     *
     * @return array<string, string> clave => etiqueta
     */
    public function columnOptionsFor(User $user): array
    {
        return collect($this->columns())
            ->reject(fn (array $column): bool => $column['owner'] && ! $user->can('view_any_owner'))
            ->map(fn (array $column): string => $column['label'])
            ->all();
    }

    /**
     * @param  array{species?: ?string, vet_id?: int|string|null}  $filters
     * @return Builder<Model>
     */
    public function query(CarbonInterface $from, CarbonInterface $to, array $filters, User $user): Builder
    {
        $model = $this->model();
        $column = $this->dateColumn();

        $query = $model::query()
            ->with($this === self::Pets ? ['owner', 'user'] : ['pet.owner', 'user'])
            ->whereDate($column, '>=', $from->toDateString())
            ->whereDate($column, '<=', $to->toDateString())
            ->orderBy($column);

        if (filled($filters['species'] ?? null)) {
            $species = $filters['species'];
            $this === self::Pets
                ? $query->where('species', $species)
                : $query->whereHas('pet', fn (Builder $pets) => $pets->where('species', $species));
        }

        if ($this->isScopedToOwnRecords() && $user->seesOnlyOwnRecords()) {
            $query->where('user_id', $user->id);
        } elseif (filled($filters['vet_id'] ?? null)) {
            $query->where('user_id', $filters['vet_id']);
        }

        return $query;
    }
}
