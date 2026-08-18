<?php

namespace App\Filament\Widgets;

use App\Enums\PetSpecies;
use App\Models\Pet;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Gráfico de barras horizontales con la cantidad de mascotas por especie.
 * La clínica atiende las 8 especies del enum PetSpecies (no solo caninos y
 * felinos) — a diferencia de un set fijo de stat cards, esto se ajusta solo
 * si se agrega una especie nueva al enum.
 */
class PetSpeciesOverview extends ChartWidget
{
    protected static ?int $sort = 0;

    protected static ?string $heading = 'Mascotas por especie';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_pet') ?? false;
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Total: '.Pet::query()->count().' mascotas registradas';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $counts = Pet::query()
            ->selectRaw('species, count(*) as aggregate')
            ->groupBy('species')
            ->pluck('aggregate', 'species');

        $species = collect(PetSpecies::cases())
            ->map(fn (PetSpecies $case) => [
                'label' => $case->getLabel(),
                'count' => (int) ($counts[$case->value] ?? 0),
            ])
            ->sortByDesc('count')
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Mascotas',
                    'data' => $species->pluck('count')->all(),
                    'backgroundColor' => '#3987e5',
                    'borderRadius' => 4,
                ],
            ],
            'labels' => $species->pluck('label')->all(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
            ],
            'scales' => [
                'x' => [
                    'ticks' => ['precision' => 0],
                ],
            ],
        ];
    }
}
