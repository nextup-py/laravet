<?php

namespace App\Filament\Resources;

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
                            ->options([
                                'Male' => 'Masculino',
                                'Female' => 'Femenino',
                            ])
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
                            ->label('Department id')
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
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('ci')
                    ->label('CI')
                    ->sortable()
                    ->searchable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('first_name')
                    ->label('Nombre(s)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->label('Apellido(s)')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label('Género')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->label('Teléfono')
                    ->sortable()
                    ->searchable(),
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
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
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\DeleteAction::make(),
                ]),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
