<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ClinicRoles;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\UserResource\RelationManagers;
use App\Models\City;
use App\Models\Department;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

/**
 * Gestión de usuarios del panel (veterinarios, asistentes y administradores).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationGroup = 'Configuración';

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'email'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('roles');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Correo' => $record->email,
            'Rol' => $record->roles->map(fn ($role): string => self::roleOptions()[$role->name] ?? $role->name)->implode(', ') ?: '—',
        ];
    }

    /**
     * Opciones de rol reutilizadas por el formulario, la columna y el filtro de rol.
     * Lee los roles reales de la base de datos (gestionables desde "Configuración
     * → Roles y permisos"), así que un rol nuevo que cree un admin aparece acá sin
     * tocar código. Los 3 roles sembrados por defecto muestran un label en español;
     * cualquier rol custom muestra su nombre tal cual lo escribió el admin.
     */
    public static function roleOptions(): array
    {
        $defaultLabels = [
            ClinicRoles::ADMIN => 'Administrador',
            ClinicRoles::VETERINARIAN => 'Veterinario',
            ClinicRoles::ASSISTANT => 'Asistente',
        ];

        return Role::query()
            ->orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name): array => [$name => $defaultLabels[$name] ?? $name])
            ->all();
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make('Datos personales')
                    ->columns(3)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nombre')
                            ->string()
                            ->required(),
                        Forms\Components\TextInput::make('email')
                            ->label('Correo electrónico')
                            ->email()
                            ->unique(ignoreRecord: true)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (?string $state, Set $set) => $set('email', Str::lower(trim((string) $state))))
                            ->dehydrateStateUsing(fn (?string $state): string => Str::lower(trim((string) $state)))
                            ->required(),
                        Forms\Components\Select::make('role')
                            ->label('Rol')
                            ->options(self::roleOptions())
                            ->disabled(fn (?User $record): bool => (bool) $record?->is(auth()->user()))
                            ->helperText(fn (?User $record): ?string => $record?->is(auth()->user())
                                ? 'No puede cambiar su propio rol.'
                                : null)
                            ->rule(fn (?User $record): \Closure => function (string $attribute, $value, \Closure $fail) use ($record) {
                                if ($record?->hasRole(ClinicRoles::ADMIN)
                                    && $value !== ClinicRoles::ADMIN
                                    && User::role(ClinicRoles::ADMIN)->count() <= 1) {
                                    $fail('No se puede quitar el rol al único administrador.');
                                }
                            })
                            ->required(),
                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->label('Contraseña')
                            ->helperText(fn (string $operation): string => $operation === 'create'
                                ? 'Mínimo 8 caracteres.'
                                : 'Déjela vacía para no cambiarla. Mínimo 8 caracteres.')
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn ($state): bool => filled($state))
                            ->minLength(8)
                            ->confirmed(),
                        Forms\Components\TextInput::make('password_confirmation')
                            ->password()
                            ->revealable()
                            ->label('Confirmar contraseña')
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                    ]),

                Section::make('Datos del domicilio')
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
                            ->columnSpanFull()
                            ->required(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo electrónico')
                    ->icon('heroicon-m-envelope')
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::roleOptions()[$state] ?? $state ?? '—'),
                Tables\Columns\TextColumn::make('city.name')
                    ->label('Ciudad')
                    ->placeholder('—')
                    ->sortable(),
                Tables\Columns\TextColumn::make('consultations_count')
                    ->label('Consultas')
                    ->counts('consultations')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('surgeries_count')
                    ->label('Cirugías')
                    ->counts('surgeries')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vaccinations_count')
                    ->label('Vacunaciones')
                    ->counts('vaccinations')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tests_count')
                    ->label('Pruebas')
                    ->counts('tests')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric()
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
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Rol')
                    ->options(self::roleOptions())
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['values'] ?? null,
                        fn (Builder $q, array $roles) => $q->role($roles)
                    )),
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
                        ->when($data['department_id'] ?? null, fn (Builder $q, $id) => $q->where('department_id', $id))
                        ->when($data['city_id'] ?? null, fn (Builder $q, $id) => $q->where('city_id', $id)))
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
                Tables\Filters\TernaryFilter::make('has_activity')
                    ->label('Actividad clínica')
                    ->placeholder('Todos')
                    ->trueLabel('Con registros')
                    ->falseLabel('Sin registros')
                    ->queries(
                        true: fn (Builder $query) => $query->where(fn (Builder $q) => $q
                            ->has('pets')->orHas('consultations')->orHas('vaccinations')->orHas('surgeries')->orHas('tests')),
                        false: fn (Builder $query) => $query->doesntHave('pets')->doesntHave('consultations')
                            ->doesntHave('vaccinations')->doesntHave('surgeries')->doesntHave('tests'),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                ]),
            ]);
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
            'view' => Pages\ViewUser::route('/{record}'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
