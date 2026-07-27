<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Filament\Resources\CityResource\RelationManagers;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class CityResource extends Resource
{
    protected static ?string $model = City::class;
    protected static ?string $navigationGroup = 'Gestión del sistema';
    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';
    protected static ?int $navigationSort = 4;
    protected static ?string $modelLabel = 'ciudad';
    protected static ?string $pluralModelLabel = 'ciudades';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->translateLabel()
                    ->autofocus()
                    ->required(),
                Forms\Components\TextInput::make('population')
                    ->translateLabel()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Forms\Components\Select::make('department_id')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload()
                    ->live()
                    ->translateLabel()
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
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('population')
                    ->translateLabel()
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\NeighborhoodsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCities::route('/'),
            'create' => Pages\CreateCity::route('/create'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
