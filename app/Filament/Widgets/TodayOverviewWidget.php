<?php

namespace App\Filament\Widgets;

use App\Enums\TestStatus;
use App\Filament\Concerns\FocusesOnOwnRecords;
use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\SurgeryResource;
use App\Filament\Resources\TestResource;
use App\Models\Consultation;
use App\Models\Surgery;
use App\Models\Test;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * KPIs operativos del día a día. Cada stat se agrega solo si el usuario tiene
 * permiso de ver ese recurso — a diferencia de canView() a nivel de widget
 * (todo o nada), acá un usuario puede ver algunos indicadores y otros no
 * dentro de la misma fila, según los permisos de su rol.
 */
class TodayOverviewWidget extends BaseWidget
{
    use FocusesOnOwnRecords;

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user?->can('view_any_consultation')
            || $user?->can('view_any_surgery')
            || $user?->can('view_any_test');
    }

    /**
     * Filtro «Veterinario» para que, al abrir la lista desde una tarjeta, el veterinario vea solo lo suyo
     * (igual que el número de la tarjeta).
     *
     * @return array<string, array<string, int|string>>
     */
    private static function ownerFilter(): array
    {
        return static::focusesOnOwnRecords() ? ['user_id' => ['value' => auth()->id()]] : [];
    }

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user?->can('view_any_consultation')) {
            $stats[] = Stat::make(self::ownershipPrefix('consultas de hoy'), self::scopeToOwnRecords(Consultation::query())->whereDate('consultation_date', today())->count())
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->url(ConsultationResource::getUrl('index', ['tableFilters' => ['consultation_date' => ['from' => today()->toDateString(), 'until' => today()->toDateString()]] + self::ownerFilter()]));
        }

        if ($user?->can('view_any_surgery')) {
            $stats[] = Stat::make(self::ownershipPrefix('cirugías próximas'), self::scopeToOwnRecords(Surgery::query())->upcoming()->count())
                ->icon('heroicon-o-scissors')
                ->color('warning')
                ->url(SurgeryResource::getUrl('index', ['tableFilters' => ['date' => ['from' => today()->toDateString(), 'until' => today()->addDays(7)->toDateString()]] + self::ownerFilter()]));
        }

        if ($user?->can('view_any_test')) {
            $stats[] = Stat::make(self::ownershipPrefix('pruebas sin resultado'), self::scopeToOwnRecords(Test::query())->withoutResult()->count())
                ->icon('heroicon-o-beaker')
                ->color('danger')
                ->url(TestResource::getUrl('index', ['activeTab' => TestStatus::Pending->getLabel(), 'tableFilters' => self::ownerFilter()]));
        }

        return $stats;
    }
}
