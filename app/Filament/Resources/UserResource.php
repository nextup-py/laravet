<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\City;
use App\Models\Neighborhood;
use App\Models\User;
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

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Administración';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'usuario';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make(__('Personal information'))
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->translateLabel()
                            ->string()
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->translateLabel()
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->hiddenOn('edit')
                            ->translateLabel()
                            ->required(),
                        Forms\Components\Select::make('role')
                            ->label('Rol')
                            ->options([
                                'admin' => 'Administrador',
                                'veterinarian' => 'Veterinario',
                                'assistant' => 'Asistente',
                            ])
                            ->required(),
                    ]),

                Section::make(__('Address information'))
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
                            ->columnSpanFull()
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
                    ->sortable()
                    ->numeric(),
                Tables\Columns\TextColumn::make('name')
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->translateLabel()
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'admin' => 'Administrador',
                        'veterinarian' => 'Veterinario',
                        'assistant' => 'Asistente',
                        default => $state ?? '—',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->translateLabel()
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->translateLabel()
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('roles')
                    ->relationship('roles', 'name')
                    ->label('Rol')
                    ->options([
                        'admin' => 'Administrador',
                        'veterinarian' => 'Veterinario',
                        'assistant' => 'Asistente',
                    ]),
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

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\PetsRelationManager::class,
            RelationManagers\VaccinationsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
