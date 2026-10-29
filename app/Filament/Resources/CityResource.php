<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CityResource\Pages;
use App\Filament\Resources\CityResource\RelationManagers;
use App\Models\City;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rules\Unique;

/**
 * Gestión de ciudades paraguayas (jerarquía Departamento → Ciudad → Barrio).
 */
class CityResource extends Resource
{
    protected static ?string $model = City::class;

    protected static ?string $slug = 'ciudades';

    protected static ?string $navigationGroup = 'Catálogos';

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'ciudad';

    protected static ?string $pluralModelLabel = 'ciudades';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('department');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Departamento' => $record->department->name];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('department_id')
                    ->relationship('department', 'name')
                    ->default(fn () => request()->query('department_id'))
                    ->searchable()
                    ->preload()
                    ->live()
                    ->label('Departamento')
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->autofocus()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('department_id', $get('department_id')),
                    )
                    ->validationMessages(['unique' => 'Ya existe una ciudad con ese nombre en el departamento.'])
                    ->required(),
                Forms\Components\TextInput::make('population')
                    ->label('Población')
                    ->helperText('Opcional: déjela vacía si se desconoce.')
                    ->integer()
                    ->minValue(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('population')
                    ->label('Población')
                    ->numeric(thousandsSeparator: '.', decimalSeparator: ',')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('neighborhoods_count')
                    ->label('Barrios')
                    ->counts('neighborhoods')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owners_count')
                    ->label('Propietarios')
                    ->counts('owners')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->counts('users')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->numeric()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizada el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->label('Departamento')
                    ->relationship('department', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\TernaryFilter::make('in_use')
                    ->label('Uso')
                    ->placeholder('Todas')
                    ->trueLabel('En uso')
                    ->falseLabel('Sin uso')
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn (Builder $q) => $q->has('owners')->orHas('users')),
                        false: fn (Builder $query) => $query->doesntHave('owners')->doesntHave('users'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('addNeighborhood')
                        ->label('Agregar barrio')
                        ->icon('heroicon-o-plus')
                        ->visible(fn (): bool => NeighborhoodResource::canCreate())
                        ->url(fn (City $record): string => NeighborhoodResource::getUrl('create', ['city_id' => $record->getKey()])),
                ]),
            ]);
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
            'view' => Pages\ViewCity::route('/{record}'),
            'edit' => Pages\EditCity::route('/{record}/edit'),
        ];
    }
}
