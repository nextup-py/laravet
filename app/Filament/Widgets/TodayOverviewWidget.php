<?php

namespace App\Filament\Widgets;

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
    protected static ?int $sort = 1;

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
            $stats[] = Stat::make('Consultas de hoy', Consultation::query()->whereDate('consultation_date', today())->count())
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info');
        }

        if ($user?->can('view_any_surgery')) {
            $stats[] = Stat::make('Cirugías próximas', Surgery::query()->upcoming()->count())
                ->icon('heroicon-o-scissors')
                ->color('warning');
        }

        if ($user?->can('view_any_test')) {
            $stats[] = Stat::make('Pruebas sin resultado', Test::query()->withoutResult()->count())
                ->icon('heroicon-o-beaker')
                ->color('danger');
        }

        return $stats;
    }
}
