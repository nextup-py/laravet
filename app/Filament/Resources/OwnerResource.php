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
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class OwnerResource extends Resource
{
    protected static ?string $model = Owner::class;

    protected static ?string $navigationGroup = 'Pacientes';

    protected static ?string $navigationIcon = 'heroicon-o-user';

    protected static ?string $modelLabel = 'propietario';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make(__('Personal information'))
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('ci')
                            ->label('CI')
                            ->required()
                            ->integer()
                            ->minValue(1)
                            ->unique(),
                        Forms\Components\TextInput::make('first_name')
                            ->translateLabel()
                            ->required()
                            ->string(),
                        Forms\Components\TextInput::make('last_name')
                            ->translateLabel()
                            ->required()
                            ->string(),
                        Forms\Components\Radio::make('gender')
                            ->translateLabel()
                            ->required()
                            ->options([
                                'Male' => __('Male'),
                                'Female' => __('Female'),
                            ])
                            ->inline()
                            ->inlineLabel(false),
                        Forms\Components\TextInput::make('email')
                            ->translateLabel()
                            ->email()
                            ->required(),
                        Forms\Components\TextInput::make('phone')
                            ->translateLabel()
                            ->tel()
                            ->required(),
                    ]),

                Section::make(__('Address information'))
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
                            ->translateLabel()
                            ->required(),
                        Forms\Components\Select::make('city_id')
                            ->options(fn (Get $get): Collection => City::query()->where('department_id', $get('department_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('neighborhood_id', null))
                            ->label(__('City'))
                            ->translateLabel()
                            ->required(),
                        Forms\Components\Select::make('neighborhood_id')
                            ->options(fn (Get $get): Collection => Neighborhood::query()->where('city_id', $get('city_id'))->pluck('name', 'id'))
                            ->searchable()
                            ->preload()
                            ->label(__('Neighborhood'))
                            ->translateLabel()
                            ->required(),
                        Forms\Components\TextInput::make('address')
                            ->translateLabel()
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
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('last_name')
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('gender')
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('phone')
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('department.name')
                    ->translateLabel()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('city.name')
                    ->translateLabel()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('neighborhood.name')
                    ->translateLabel()
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('address')
                    ->translateLabel()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->translateLabel()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->label(__('Created'))
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->label(__('Updated'))
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
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
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
            'edit' => Pages\EditOwner::route('/{record}/edit'),
        ];
    }
}
