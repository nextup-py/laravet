<?php

namespace App\Filament\Widgets;

use App\Enums\PetSpecies;
use App\Models\Pet;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class PetSpeciesOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Caninos', Pet::query()->where('species', PetSpecies::Canino)->count())
                ->icon('heroicon-o-identification')
                ->color('info'),
            Stat::make('Felinos', Pet::query()->where('species', PetSpecies::Felino)->count())
                ->icon('heroicon-o-face-smile')
                ->color('warning'),
            Stat::make('Total', Pet::query()->count())
                ->icon('heroicon-o-rectangle-stack')
                ->color('success'),
        ];
    }
}
