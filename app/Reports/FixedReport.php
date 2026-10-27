<?php

namespace App\Reports;

use App\Enums\VaccinationStatus;
use App\Models\Consultation;
use App\Models\Owner;
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
 * Reportes fijos de la clínica: se eligen de una lista y solo piden el rango de
 * fechas (cuando aplica). Cada uno exige permiso sobre los registros que resume,
 * y el veterinario ve únicamente lo suyo en consultas, cirugías y pruebas.
 */
enum FixedReport: string
{
    case ClinicActivity = 'clinic-activity';
    case ActivityByVet = 'activity-by-vet';
    case PetsBySpecies = 'pets-by-species';
    case VaccinationsDue = 'vaccinations-due';
    case SurgeriesByType = 'surgeries-by-type';
    case TestsByType = 'tests-by-type';

    public function label(): string
    {
        return match ($this) {
            self::ClinicActivity => 'Actividad de la clínica',
            self::ActivityByVet => 'Actividad por veterinario',
            self::PetsBySpecies => 'Mascotas por especie',
            self::VaccinationsDue => 'Vacunas vencidas y por vencer',
            self::SurgeriesByType => 'Cirugías por tipo',
            self::TestsByType => 'Pruebas por tipo y estado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ClinicActivity => 'Cantidad de consultas, vacunas, cirugías y pruebas realizadas, y de mascotas y propietarios registrados en el período.',
            self::ActivityByVet => 'Consultas, vacunas, cirugías y pruebas del período, por veterinario.',
            self::PetsBySpecies => 'Mascotas activas e inactivas por especie, a hoy.',
            self::VaccinationsDue => 'Vacunas vigentes vencidas o que vencen en 7 días, a hoy.',
            self::SurgeriesByType => 'Cirugías del período agrupadas por tipo.',
            self::TestsByType => 'Pruebas del período por tipo, con resultado y pendientes.',
        };
    }

    public function usesDateRange(): bool
    {
        return ! in_array($this, [self::PetsBySpecies, self::VaccinationsDue], true);
    }

    /**
     * Permiso mínimo para ver el reporte. La actividad general se arma con lo que el usuario pueda ver.
     */
    public function isAvailableTo(User $user): bool
    {
        return match ($this) {
            self::ClinicActivity => true,
            self::ActivityByVet => $user->can('view_any_consultation') || $user->can('view_any_surgery') || $user->can('view_any_test') || $user->can('view_any_vaccination'),
            self::PetsBySpecies => $user->can('view_any_pet'),
            self::VaccinationsDue => $user->can('view_any_vaccination'),
            self::SurgeriesByType => $user->can('view_any_surgery'),
            self::TestsByType => $user->can('view_any_test'),
        };
    }

    public function generate(CarbonInterface $from, CarbonInterface $to, User $user): ReportResult
    {
        $period = $this->usesDateRange()
            ? 'Del '.$from->format('d/m/Y').' al '.$to->format('d/m/Y')
            : 'A '.now()->format('d/m/Y');

        return match ($this) {
            self::ClinicActivity => new ReportResult($this->label(), ['Concepto', 'Cantidad'], $this->clinicActivity($from, $to, $user), subtitle: $period),
            self::ActivityByVet => new ReportResult($this->label(), ['Veterinario', 'Consultas', 'Vacunas aplicadas', 'Cirugías', 'Pruebas'], $this->activityByVet($from, $to, $user), subtitle: $period),
            self::PetsBySpecies => new ReportResult($this->label(), ['Especie', 'Activas', 'Inactivas', 'Total'], $this->petsBySpecies(), subtitle: $period),
            self::VaccinationsDue => $this->vaccinationsDue($user, $period),
            self::SurgeriesByType => new ReportResult($this->label(), ['Tipo de cirugía', 'Cantidad'], $this->surgeriesByType($from, $to, $user), subtitle: $period),
            self::TestsByType => new ReportResult($this->label(), ['Tipo de prueba', 'Total', 'Con resultado', 'Pendientes'], $this->testsByType($from, $to, $user), subtitle: $period),
        };
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function clinicActivity(CarbonInterface $from, CarbonInterface $to, User $user): array
    {
        $rows = [];

        if ($user->can('view_any_consultation')) {
            $rows[] = ['Consultas realizadas', $this->ownRecords(Consultation::query(), $user)->whereDate('consultation_date', '>=', $from->toDateString())->whereDate('consultation_date', '<=', $to->toDateString())->count()];
        }

        if ($user->can('view_any_vaccination')) {
            $rows[] = ['Vacunas aplicadas', Vaccination::query()->whereDate('application_date', '>=', $from->toDateString())->whereDate('application_date', '<=', $to->toDateString())->count()];
        }

        if ($user->can('view_any_surgery')) {
            $rows[] = ['Cirugías realizadas', $this->ownRecords(Surgery::query(), $user)->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->count()];
        }

        if ($user->can('view_any_test')) {
            $rows[] = ['Pruebas laboratoriales', $this->ownRecords(Test::query(), $user)->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())->count()];
        }

        if ($user->can('view_any_pet')) {
            $rows[] = ['Mascotas registradas', Pet::query()->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())->count()];
        }

        if ($user->can('view_any_owner')) {
            $rows[] = ['Propietarios registrados', Owner::query()->whereDate('created_at', '>=', $from->toDateString())->whereDate('created_at', '<=', $to->toDateString())->count()];
        }

        return $rows;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function activityByVet(CarbonInterface $from, CarbonInterface $to, User $user): array
    {
        return User::query()
            ->whereHas('roles')
            ->when($user->seesOnlyOwnRecords(), fn (Builder $users) => $users->whereKey($user->id))
            ->withCount([
                'consultations as consultations_in_range' => fn (Builder $q) => $q->whereDate('consultation_date', '>=', $from->toDateString())->whereDate('consultation_date', '<=', $to->toDateString()),
                'vaccinations as vaccinations_in_range' => fn (Builder $q) => $q->whereDate('application_date', '>=', $from->toDateString())->whereDate('application_date', '<=', $to->toDateString()),
                'surgeries as surgeries_in_range' => fn (Builder $q) => $q->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString()),
                'tests as tests_in_range' => fn (Builder $q) => $q->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString()),
            ])
            ->orderBy('name')
            ->get()
            ->filter(fn (User $vet): bool => $vet->consultations_in_range + $vet->vaccinations_in_range + $vet->surgeries_in_range + $vet->tests_in_range > 0)
            ->map(fn (User $vet): array => [$vet->name, $vet->consultations_in_range, $vet->vaccinations_in_range, $vet->surgeries_in_range, $vet->tests_in_range])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function petsBySpecies(): array
    {
        return Pet::query()
            ->selectRaw('species, sum(case when active then 1 else 0 end) as active_count, sum(case when active then 0 else 1 end) as inactive_count')
            ->groupBy('species')
            ->get()
            ->map(fn (Pet $pet): array => [
                $pet->species->getLabel(),
                (int) $pet->active_count,
                (int) $pet->inactive_count,
                (int) $pet->active_count + (int) $pet->inactive_count,
            ])
            ->sortByDesc(fn (array $row): int => $row[3])
            ->values()
            ->all();
    }

    private function vaccinationsDue(User $user, string $period): ReportResult
    {
        $showOwner = $user->can('view_any_owner');

        $rows = Vaccination::query()
            ->with(['pet.owner'])
            ->actionable()
            ->orderBy('next_application')
            ->limit(5000)
            ->get()
            ->map(fn (Vaccination $vaccination): array => [
                $vaccination->pet->name,
                $vaccination->vaccine,
                Carbon::parse($vaccination->next_application)->format('d/m/Y'),
                $vaccination->status === VaccinationStatus::Overdue ? 'Vencida' : 'Por vencer',
                ...($showOwner ? [$vaccination->pet->owner?->full_name, $vaccination->pet->owner?->phone] : []),
            ])
            ->all();

        return new ReportResult(
            $this->label(),
            ['Mascota', 'Vacuna', 'Próxima aplicación', 'Estado', ...($showOwner ? ['Propietario', 'Teléfono'] : [])],
            $rows,
            includesOwnerData: $showOwner,
            subtitle: $period,
        );
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function surgeriesByType(CarbonInterface $from, CarbonInterface $to, User $user): array
    {
        return $this->ownRecords(Surgery::query(), $user)
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->selectRaw('type, count(*) as total')
            ->groupBy('type')
            ->orderByDesc('total')
            ->orderBy('type')
            ->get()
            ->map(fn (Surgery $surgery): array => [$surgery->type, (int) $surgery->total])
            ->all();
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    private function testsByType(CarbonInterface $from, CarbonInterface $to, User $user): array
    {
        return $this->ownRecords(Test::query(), $user)
            ->whereDate('date', '>=', $from->toDateString())->whereDate('date', '<=', $to->toDateString())
            ->get()
            ->groupBy('type')
            ->map(function ($tests, string $type): array {
                $pending = $tests->filter(fn (Test $test): bool => empty($test->result))->count();

                return [$type, $tests->count(), $tests->count() - $pending, $pending];
            })
            ->sortByDesc(fn (array $row): int => $row[1])
            ->values()
            ->all();
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    private function ownRecords(Builder $query, User $user): Builder
    {
        return $user->seesOnlyOwnRecords() ? $query->where('user_id', $user->id) : $query;
    }
}
