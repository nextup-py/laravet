<?php

namespace App\Filament\Resources;

use App\Enums\OwnerGender;
use App\Filament\Resources\OwnerResource\Pages;
use App\Filament\Resources\OwnerResource\RelationManagers;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\Owner;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Gestión de propietarios de mascotas.
 */
class OwnerResource extends Resource
{
    protected static ?string $model = Owner::class;

    protected static ?string $navigationGroup = 'Pacientes';

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'propietario';

    protected static ?string $recordTitleAttribute = 'first_name';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? static::getGlobalSearchResultTitle($record) : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['first_name', 'last_name', 'ci', 'phone'];
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return $record->full_name;
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'CI' => number_format($record->ci, 0, ',', '.'),
            'Teléfono' => $record->phone,
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make('Datos personales')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('ci')
                            ->label('CI')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->unique(ignoreRecord: true),
                        Forms\Components\TextInput::make('first_name')
                            ->label('Nombre(s)')
                            ->required()
                            ->string(),
                        Forms\Components\TextInput::make('last_name')
                            ->label('Apellido(s)')
                            ->required()
                            ->string(),
                        Forms\Components\Radio::make('gender')
                            ->label('Género')
                            ->required()
                            ->options(OwnerGender::class)
                            ->inline()
                            ->inlineLabel(false),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Forms\Components\TextInput::make('phone')
                            ->label('Teléfono')
                            ->tel()
                            ->regex('/^(\+595|0)\d{8,9}$/')
                            ->helperText('Sin espacios ni guiones. Ej.: 0981123456 o +595981123456.')
                            ->validationMessages([
                                'regex' => 'Ingrese un teléfono paraguayo válido, ej.: 0981123456 o +595981123456.',
                            ])
                            ->required(),
                    ]),

                Section::make('Datos del domicilio')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('department_id')
                            ->relationship('department', 'name')
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(function (Set $set) {
                                $set('city_id', null);
                                $set('neighborhood_id', null);
                            })
                            ->label('Departamento')
                            ->required(),
                        Forms\Components\Select::make('city_id')
                            ->options(fn (Get $get): Collection => City::query()->where('department_id', $get('department_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('neighborhood_id', null))
                            ->label('Ciudad')
                            ->required(),
                        Forms\Components\Select::make('neighborhood_id')
                            ->options(fn (Get $get): Collection => Neighborhood::query()->where('city_id', $get('city_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->label('Barrio')
                            ->required(),
                        Forms\Components\TextInput::make('address')
                            ->label('Dirección')
                            ->required(),
                    ]),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('full_name')
                    ->label('Propietario')
                    ->description(fn (Owner $record): string => 'CI '.number_format($record->ci, 0, ',', '.'))
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->sortable(['first_name', 'last_name']),
                Tables\Columns\TextColumn::make('pets_count')
                    ->label('Mascotas')
                    ->counts('pets')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label('Género')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->icon('heroicon-m-chat-bubble-left-ellipsis')
                    ->url(fn (Owner $record): string => $record->whatsapp_url, shouldOpenInNewTab: true)
                    ->tooltip('Abrir en WhatsApp')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('department.name')
                    ->label('Departamento')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ciudad')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('neighborhood.name')
                    ->label('Barrio')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('address')
                    ->label('Dirección')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('department_id')
                    ->relationship('department', 'name')
                    ->label('Departamento')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('ubicacion')
                    ->form([
                        Forms\Components\Select::make('city_id')
                            ->label('Ciudad')
                            ->options(fn (): Collection => City::query()->orderBy('name')->pluck('name', 'id'))
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('neighborhood_id', null)),
                        Forms\Components\Select::make('neighborhood_id')
                            ->label('Barrio')
                            ->options(fn (Get $get): Collection => Neighborhood::query()->where('city_id', $get('city_id'))->orderBy('name')->pluck('name', 'id'))
                            ->searchable(),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['city_id'], fn (Builder $q, $id) => $q->where('city_id', $id))
                        ->when($data['neighborhood_id'], fn (Builder $q, $id) => $q->where('neighborhood_id', $id)))
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];

                        if ($data['city_id'] ?? null) {
                            $indicators[] = 'Ciudad: '.City::find($data['city_id'])?->name;
                        }

                        if ($data['neighborhood_id'] ?? null) {
                            $indicators[] = 'Barrio: '.Neighborhood::find($data['neighborhood_id'])?->name;
                        }

                        return $indicators;
                    }),
                Tables\Filters\SelectFilter::make('gender')
                    ->label('Género')
                    ->options(OwnerGender::class),
                Tables\Filters\TernaryFilter::make('has_pets')
                    ->label('Mascotas')
                    ->placeholder('Todos')
                    ->trueLabel('Con mascotas')
                    ->falseLabel('Sin mascotas')
                    ->queries(
                        true: fn (Builder $query) => $query->has('pets'),
                        false: fn (Builder $query) => $query->doesntHave('pets'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('registerPet')
                        ->label('Registrar mascota')
                        ->icon('heroicon-o-plus')
                        ->visible(fn (): bool => PetResource::canCreate())
                        ->url(fn (Owner $record): string => PetResource::getUrl('create', ['owner_id' => $record->getKey()])),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PetsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOwners::route('/'),
            'create' => Pages\CreateOwner::route('/create'),
            'view' => Pages\ViewOwner::route('/{record}'),
            'edit' => Pages\EditOwner::route('/{record}/edit'),
        ];
    }
}
