<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\VaccinationResource;
use App\Models\Vaccination;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class UpcomingVaccinationsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_vaccination') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Vacunas próximas a vencer')
            ->emptyStateHeading('No hay vacunas próximas a vencer')
            ->emptyStateIcon('heroicon-o-shield-check')
            ->query(
                Vaccination::query()
                    ->with(['pet', 'pet.owner'])
                    ->upcomingOrOverdue()
                    ->orderBy('next_application')
            )
            ->recordUrl(fn (Vaccination $record) => VaccinationResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pet.owner.full_name')
                    ->label('Propietario'),
                Tables\Columns\TextColumn::make('vaccine')
                    ->label('Vacuna'),
                Tables\Columns\TextColumn::make('next_application')
                    ->label('Próxima aplicación')
                    ->date('d/m/Y')
                    ->color(fn ($state) => match (true) {
                        Carbon::parse($state)->isPast() => 'danger',
                        Carbon::parse($state)->lte(now()->addDays(3)) => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
            ])
            ->paginated([5, 10, 25]);
    }
}
