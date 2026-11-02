<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\OpensViewFirst;
use App\Filament\Resources\NeighborhoodResource\Pages;
use App\Models\City;
use App\Models\Department;
use App\Models\Neighborhood;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rules\Unique;

/**
 * Gestión de barrios paraguayos (jerarquía Departamento → Ciudad → Barrio).
 */
class NeighborhoodResource extends Resource
{
    use OpensViewFirst;

    protected static ?string $model = Neighborhood::class;

    protected static ?string $slug = 'barrios';

    protected static ?string $navigationGroup = 'Catálogos';

    protected static ?string $navigationIcon = 'heroicon-o-home-modern';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'barrio';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('city.department');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Ciudad' => $record->city->name,
            'Departamento' => $record->city->department->name,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // Solo sirve para filtrar las ciudades; no se guarda (el barrio pertenece a una ciudad).
                Forms\Components\Select::make('department_id')
                    ->label('Departamento')
                    ->options(fn (): Collection => Department::query()->orderBy('name')->pluck('name', 'id'))
                    ->afterStateHydrated(fn (Set $set, ?Neighborhood $record) => $record && $set('department_id', $record->city?->department_id))
                    ->default(fn () => City::find(request()->query('city_id'))?->department_id)
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('city_id', null))
                    ->dehydrated(false)
                    ->required(),
                Forms\Components\Select::make('city_id')
                    ->label('Ciudad')
                    ->default(fn () => request()->query('city_id'))
                    ->options(fn (Get $get): Collection => City::query()
                        ->where('department_id', $get('department_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->searchable()
                    ->live()
                    ->required(),
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->maxLength(255)
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule, Get $get): Unique => $rule->where('city_id', $get('city_id')),
                    )
                    ->validationMessages(['unique' => 'Ya existe un barrio con ese nombre en la ciudad.'])
                    ->required(),
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
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.department.name')
                    ->label('Departamento')
                    ->searchable(),
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
                    ->label('Registrado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\Filter::make('ubicacion')
                    ->form([
                        Forms\Components\Select::make('department_id')
                            ->label('Departamento')
                            ->options(fn (): Collection => Department::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('city_id', null)),
                        Forms\Components\Select::make('city_id')
                            ->label('Ciudad')
                            ->options(fn (Get $get): Collection => City::query()
                                ->when($get('department_id'), fn (Builder $q, $id) => $q->where('department_id', $id))
                                ->orderBy('name')
                                ->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['city_id'] ?? null, fn (Builder $q, $id) => $q->where('city_id', $id))
                        ->when(
                            ($data['department_id'] ?? null) && ! ($data['city_id'] ?? null),
                            fn (Builder $q) => $q->whereHas('city', fn (Builder $c) => $c->where('department_id', $data['department_id']))
                        ))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['department_id'] ?? null) {
                            $indicators[] = 'Departamento: '.Department::find($data['department_id'])?->name;
                        }

                        if ($data['city_id'] ?? null) {
                            $indicators[] = 'Ciudad: '.City::find($data['city_id'])?->name;
                        }

                        return $indicators;
                    }),
                Tables\Filters\TernaryFilter::make('in_use')
                    ->label('Uso')
                    ->placeholder('Todos')
                    ->trueLabel('En uso')
                    ->falseLabel('Sin uso')
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn (Builder $q) => $q->has('owners')->orHas('users')),
                        false: fn (Builder $query) => $query->doesntHave('owners')->doesntHave('users'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
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
            'view' => Pages\ViewNeighborhood::route('/{record}'),
            'edit' => Pages\EditNeighborhood::route('/{record}/edit'),
        ];
    }
}
