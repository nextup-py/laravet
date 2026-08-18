<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\SurgeryResource;
use App\Models\Surgery;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class UpcomingSurgeriesWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_surgery') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Cirugías próximas')
            ->emptyStateHeading('No hay cirugías programadas en los próximos días')
            ->emptyStateIcon('heroicon-o-scissors')
            ->query(
                Surgery::query()
                    ->with(['pet', 'pet.owner'])
                    ->upcoming()
                    ->orderBy('date')
            )
            ->recordUrl(fn (Surgery $record) => SurgeryResource::getUrl('view', ['record' => $record]))
            ->columns([
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->searchable(),
                Tables\Columns\TextColumn::make('pet.owner.full_name')
                    ->label('Propietario'),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo'),
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
            ])
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }
}
