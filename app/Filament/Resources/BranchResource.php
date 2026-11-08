<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\OpensViewFirst;
use App\Filament\Resources\BranchResource\Pages;
use App\Models\Branch;
use App\Models\City;
use App\Models\Neighborhood;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Sucursales de la clínica. El código de 3 dígitos es el establecimiento de la factura paraguaya.
 */
class BranchResource extends Resource
{
    use OpensViewFirst;

    protected static ?string $model = Branch::class;

    protected static ?string $slug = 'sucursales';

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'sucursal';

    protected static ?string $pluralModelLabel = 'sucursales';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getRecordTitle(?Model $record): ?string
    {
        return $record ? "{$record->code} · {$record->name}" : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['code', 'name', 'address'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Dirección' => $record->address ?: '—'];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Datos de la sucursal')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('code')
                            ->label('Código de establecimiento')
                            ->helperText('3 dígitos, el mismo que figura en la factura (001, 002…).')
                            ->regex('/^\d{3}$/')
                            ->validationMessages(['regex' => 'Ingrese 3 dígitos, por ejemplo 002.'])
                            ->length(3)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?Branch $record): bool => $record?->code === Branch::MAIN_CODE)
                            ->dehydrated(fn (?Branch $record): bool => $record?->code !== Branch::MAIN_CODE)
                            ->required(),
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->autofocus()
                            ->string()
                            ->maxLength(255)
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Habilitada')
                            ->default(true)
                            ->disabled(fn (?Branch $record): bool => $record?->code === Branch::MAIN_CODE)
                            ->helperText(fn (?Branch $record): ?string => $record?->code === Branch::MAIN_CODE
                                ? 'La sucursal principal siempre está habilitada.'
                                : 'Una sucursal deshabilitada no se puede elegir como activa.')
                            ->inline(false),
                    ]),

                Forms\Components\Section::make('Contacto')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->regex('/^(\+595|0)\d{8,9}$/')
                            ->helperText('Sin espacios ni guiones. Ej.: 0981123456 o +595981123456.')
                            ->validationMessages([
                                'regex' => 'Ingrese un teléfono paraguayo válido, ej.: 0981123456 o +595981123456.',
                            ])
                            ->maxLength(30),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->maxLength(255),
                    ]),

                Forms\Components\Section::make('Ubicación')
                    ->columns(3)
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->relationship(name: 'department', titleAttribute: 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('city_id', null);
                                $set('neighborhood_id', null);
                            })
                            ->label('Departamento'),
                        Forms\Components\Select::make('city_id')
                            ->options(fn (Get $get): Collection => City::query()->where('department_id', $get('department_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('neighborhood_id', null))
                            ->label('Ciudad'),
                        Forms\Components\Select::make('neighborhood_id')
                            ->options(fn (Get $get): Collection => Neighborhood::query()->where('city_id', $get('city_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->label('Barrio'),
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ciudad')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->placeholder('—')
                    ->searchable(),
                Tables\Columns\TextColumn::make('users_count')
                    ->label('Personal')
                    ->counts('users')
                    ->badge()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->label('Habilitada')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->numeric()
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
            ->defaultSort('code')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Habilitadas')
                    ->falseLabel('Deshabilitadas'),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBranches::route('/'),
            'create' => Pages\CreateBranch::route('/create'),
            'view' => Pages\ViewBranch::route('/{record}'),
            'edit' => Pages\EditBranch::route('/{record}/edit'),
        ];
    }
}
