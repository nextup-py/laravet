<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\TestResource;
use App\Models\Test;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;

class TestsWithoutResultWidget extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->can('view_any_test') ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Pruebas laboratoriales sin resultado')
            ->emptyStateHeading('No hay pruebas pendientes de resultado')
            ->emptyStateIcon('heroicon-o-beaker')
            ->query(
                Test::query()
                    ->with(['pet', 'pet.owner'])
                    ->withoutResult()
                    ->orderBy('date')
            )
            ->recordUrl(fn (Test $record) => TestResource::getUrl('view', ['record' => $record]))
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
