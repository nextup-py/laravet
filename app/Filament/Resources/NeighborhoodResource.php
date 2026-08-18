<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NeighborhoodResource\Pages;
use App\Models\Neighborhood;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Gestión de barrios paraguayos (jerarquía Departamento → Ciudad → Barrio).
 */
class NeighborhoodResource extends Resource
{
    protected static ?string $model = Neighborhood::class;

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?int $navigationSort = 6;

    protected static ?string $modelLabel = 'barrio';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                Forms\Components\Select::make('city_id')
                    ->relationship('city', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->label('Ciudad')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->numeric()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                Tables\Actions\EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNeighborhoods::route('/'),
            'create' => Pages\CreateNeighborhood::route('/create'),
            'edit' => Pages\EditNeighborhood::route('/{record}/edit'),
        ];
    }
}
