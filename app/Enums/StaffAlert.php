<?php

namespace App\Enums;

use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\VaccinationResource;
use App\Models\Surgery;
use App\Models\Test;
use App\Models\User;
use App\Models\Vaccination;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Database\Eloquent\Builder;

/**
 * Avisos que el panel le manda al personal (campanita y/o correo). Cada usuario
 * elige en su perfil por qué canales recibe cada uno; solo se ofrecen y envían
 * los de recursos que su rol puede ver.
 */
enum StaffAlert: string implements HasLabel
{
    case Vaccinations = 'vaccinations';
    case Surgeries = 'surgeries';
    case Tests = 'tests';

    public function getLabel(): string
    {
        return match ($this) {
            self::Vaccinations => 'Vacunas vencidas o por vencer',
            self::Surgeries => 'Cirugías de hoy y mañana',
            self::Tests => 'Pruebas sin resultado',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Vaccinations => 'Aviso diario con la cantidad de vacunas vigentes vencidas o que vencen en 7 días.',
            self::Surgeries => 'Aviso diario con las cirugías programadas para hoy y mañana (las tuyas, si eres veterinario).',
            self::Tests => 'Aviso diario con las pruebas laboratoriales que todavía no tienen resultado (las tuyas, si eres veterinario).',
        };
    }

    public function permission(): string
    {
        return match ($this) {
            self::Vaccinations => 'view_any_vaccination',
            self::Surgeries => 'view_any_surgery',
            self::Tests => 'view_any_test',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Vaccinations => 'heroicon-o-shield-exclamation',
            self::Surgeries => 'heroicon-o-scissors',
            self::Tests => 'heroicon-o-beaker',
        };
    }

    public function url(): string
    {
        return match ($this) {
            self::Vaccinations => VaccinationResource::getUrl('index'),
            self::Surgeries => SurgeryResource::getUrl('index'),
            self::Tests => TestResource::getUrl('index'),
        };
    }

    /**
     * Registros que motivan el aviso para ese usuario. El veterinario ve solo los
     * suyos en cirugías y pruebas, igual que en el dashboard.
     */
    public function query(User $user): Builder
    {
        return match ($this) {
            self::Vaccinations => Vaccination::query()->actionable(),
            self::Surgeries => $this->ownOnly(
                Surgery::query()->whereBetween('date', [today(), today()->addDay()->endOfDay()]),
                $user,
            ),
            self::Tests => $this->ownOnly(Test::query()->withoutResult(), $user),
        };
    }

    public function title(int $count): string
    {
        return match ($this) {
            self::Vaccinations => $count === 1 ? '1 vacuna vencida o por vencer' : "{$count} vacunas vencidas o por vencer",
            self::Surgeries => $count === 1 ? '1 cirugía hoy o mañana' : "{$count} cirugías hoy o mañana",
            self::Tests => $count === 1 ? '1 prueba sin resultado' : "{$count} pruebas sin resultado",
        };
    }

    private function ownOnly(Builder $query, User $user): Builder
    {
        return $user->seesOnlyOwnRecords() ? $query->where('user_id', $user->id) : $query;
    }
}
