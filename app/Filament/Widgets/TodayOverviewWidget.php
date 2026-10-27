<?php

namespace App\Filament\Widgets;

use App\Filament\Concerns\FocusesOnOwnRecords;
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

    protected function getStats(): array
    {
        $user = auth()->user();
        $stats = [];

        if ($user?->can('view_any_consultation')) {
            $stats[] = Stat::make(self::ownershipPrefix('consultas de hoy'), self::scopeToOwnRecords(Consultation::query())->whereDate('consultation_date', today())->count())
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info');
        }

        if ($user?->can('view_any_surgery')) {
            $stats[] = Stat::make(self::ownershipPrefix('cirugías próximas'), self::scopeToOwnRecords(Surgery::query())->upcoming()->count())
                ->icon('heroicon-o-scissors')
                ->color('warning');
        }

        if ($user?->can('view_any_test')) {
            $stats[] = Stat::make(self::ownershipPrefix('pruebas sin resultado'), self::scopeToOwnRecords(Test::query())->withoutResult()->count())
                ->icon('heroicon-o-beaker')
                ->color('danger');
        }

        return $stats;
    }
}
