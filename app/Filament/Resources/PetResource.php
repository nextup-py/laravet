<?php

namespace App\Filament\Resources;

use App\Enums\PetGender;
use App\Enums\PetReproductionStatus;
use App\Enums\PetSize;
use App\Enums\PetSpecies;
use App\Filament\Concerns\OpensViewFirst;
use App\Filament\Resources\PetResource\Pages;
use App\Filament\Resources\PetResource\RelationManagers;
use App\Models\Owner;
use App\Models\Pet;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Gestión de mascotas y su historial clínico asociado.
 */
class PetResource extends Resource
{
    use OpensViewFirst;

    protected static ?string $model = Pet::class;

    protected static ?string $slug = 'mascotas';

    protected static ?string $navigationGroup = 'Pacientes';

    protected static ?string $navigationIcon = 'heroicon-o-identification';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'mascota';

    protected static ?string $recordTitleAttribute = 'name';

    public static function getGloballySearchableAttributes(): array
    {
        return OwnerResource::canViewAny()
            ? ['name', 'breed', 'owner.first_name', 'owner.last_name', 'owner.ci']
            : ['name', 'breed'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('owner');
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'Especie' => $record->species?->getLabel(),
            'Propietario' => OwnerResource::canViewAny() ? $record->owner?->full_name : null,
        ]);
    }

    /**
     * Schema base del formulario; `form()` le agrega el campo del propietario.
     */
    public static function formSchema(): array
    {
        return [
            Section::make('Datos identificatorios')
                ->columns(3)
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nombre')
                        ->string()
                        ->maxLength(255)
                        ->required(),
                    Forms\Components\Select::make('species')
                        ->label('Especie')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(PetSpecies::class)
                        ->required(),
                    Forms\Components\TextInput::make('breed')
                        ->label('Raza')
                        ->string()
                        ->maxLength(255)
                        ->required(),
                    Forms\Components\DatePicker::make('birthdate')
                        ->label('Fecha de nacimiento')
                        ->required()
                        ->native(false)
                        ->displayFormat('d/m/Y')
                        ->closeOnDateSelection()
                        ->minDate(now()->subYears(25))
                        ->maxDate(now()),
                    Forms\Components\Radio::make('gender')
                        ->label('Género')
                        ->required()
                        ->options(PetGender::class)
                        ->inline()
                        ->inlineLabel(false),
                    Forms\Components\Select::make('reproduction')
                        ->label('Reproducción')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(PetReproductionStatus::class)
                        ->required(),
                    Forms\Components\TextInput::make('id')
                        ->label('ID')
                        ->hiddenOn('create')
                        ->readOnly(),
                    Forms\Components\Toggle::make('active')
                        ->label('Activo')
                        ->hiddenOn('create')
                        ->onColor('success')
                        ->offColor('danger')
                        ->inline(false),
                ]),

            Section::make('Más información')
                ->columns(3)
                ->schema([
                    Forms\Components\Select::make('size')
                        ->label('Tamaño')
                        ->searchable()
                        ->preload()
                        ->live()
                        ->options(PetSize::class)
                        ->required(),
                    Forms\Components\TextInput::make('weight')
                        ->label('Peso')
                        ->suffix('kg')
                        ->required()
                        ->numeric()
                        ->inputMode('decimal')
                        ->minValue(0.1)
                        ->maxValue(150),
                    Forms\Components\TextInput::make('fur')
                        ->label('Pelaje')
                        ->string()
                        ->maxLength(255)
                        ->required(),
                    Forms\Components\FileUpload::make('image')
                        ->label('Imagen')
                        ->image()
                        ->imageEditor()
                        ->downloadable()
                        ->columnSpanFull()
                        ->uploadingMessage('Subiendo archivo adjunto...'),
                ]),
        ];
    }

    public static function form(Form $form): Form
    {
        $schema = self::formSchema();

        $schema[0] = $schema[0]->schema([
            Forms\Components\Select::make('owner_id')
                ->label('Propietario')
                ->relationship('owner', 'first_name')
                ->default(fn () => request()->query('owner_id'))
                ->searchable(OwnerResource::canViewAny() ? ['first_name', 'ci'] : ['first_name'])
                ->preload()
                ->live()
                ->required(),
            ...$schema[0]->getChildComponents(),
        ]);

        return $form->schema($schema);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Imagen')
                    ->circular()
                    ->defaultImageUrl(fn (Pet $record): string => $record->initials_avatar_url),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('species')
                    ->label('Especie')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('breed')
                    ->label('Raza')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('age')
                    ->label('Edad')
                    ->getStateUsing(fn (Pet $record) => $record->age)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('birthdate', $direction === 'asc' ? 'desc' : 'asc')),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('owner.first_name')
                    ->label('Propietario')
                    ->formatStateUsing(fn (Pet $record): string => OwnerResource::canView($record->owner)
                        ? $record->owner->full_name
                        : $record->owner->first_name)
                    ->url(fn (Pet $record): ?string => OwnerResource::canView($record->owner)
                        ? OwnerResource::getUrl('view', ['record' => $record->owner_id])
                        : null)
                    ->searchable(OwnerResource::canViewAny() ? ['first_name', 'last_name', 'ci'] : ['first_name'])
                    ->sortable(),
                Tables\Columns\TextColumn::make('gender')
                    ->label('Género')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('size')
                    ->label('Tamaño')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('reproduction')
                    ->label('Reproducción')
                    ->badge()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Creado a las')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Actualizado a las')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('species')
                    ->label('Especie')
                    ->options(PetSpecies::class)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('size')
                    ->label('Tamaño')
                    ->options(PetSize::class),
                Tables\Filters\SelectFilter::make('gender')
                    ->label('Género')
                    ->options(PetGender::class),
                Tables\Filters\SelectFilter::make('reproduction')
                    ->label('Reproducción')
                    ->options(PetReproductionStatus::class),
                Tables\Filters\SelectFilter::make('owner_id')
                    ->label('Propietario')
                    ->visible(fn (): bool => OwnerResource::canViewAny())
                    ->relationship('owner', 'first_name')
                    ->getOptionLabelFromRecordUsing(fn (Owner $record): string => "{$record->full_name} (CI {$record->ci})")
                    ->searchable(['first_name', 'last_name', 'ci'])
                    ->preload(),
                Tables\Filters\TernaryFilter::make('vaccination_due')
                    ->label('Vacunas')
                    ->placeholder('Todas')
                    ->trueLabel('Vencidas o por vencer (7 días)')
                    ->falseLabel('Al día')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('vaccinations', fn (Builder $q) => $q->actionable()),
                        false: fn (Builder $query) => $query->whereDoesntHave('vaccinations', fn (Builder $q) => $q->actionable()),
                        blank: fn (Builder $query) => $query,
                    ),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    ...self::registerActions(),
                    Tables\Actions\Action::make('openClinicalHistory')
                        ->label('Historia clínica PDF')
                        ->icon('heroicon-o-document-text')
                        ->visible(fn (): bool => ConsultationResource::canViewAny())
                        ->url(fn (Pet $record): string => route('filament.admin.pdf.clinical-history', $record))
                        ->openUrlInNewTab(),
                ]),
            ])->selectCurrentPageOnly();
    }

    /**
     * Atajos de fila que abren el alta de un registro clínico con la mascota precargada (`?pet_id=`).
     * Solo para mascotas activas, igual que los selects de mascota de los formularios.
     *
     * @return array<int, Tables\Actions\Action>
     */
    public static function registerActions(): array
    {
        $shortcuts = [
            'consultation' => ['Nueva consulta', 'heroicon-o-chat-bubble-left-right', ConsultationResource::class],
            'vaccination' => ['Nueva vacunación', 'heroicon-o-shield-check', VaccinationResource::class],
            'surgery' => ['Nueva cirugía', 'heroicon-o-scissors', SurgeryResource::class],
            'test' => ['Nueva prueba', 'heroicon-o-beaker', TestResource::class],
        ];

        return collect($shortcuts)
            ->map(fn (array $shortcut, string $key): Tables\Actions\Action => Tables\Actions\Action::make("register_{$key}")
                ->label($shortcut[0])
                ->icon($shortcut[1])
                ->visible(fn (Pet $record): bool => $record->active && $shortcut[2]::canCreate())
                ->url(fn (Pet $record): string => $shortcut[2]::getUrl('create', ['pet_id' => $record->getKey()])))
            ->values()
            ->all();
    }

    /**
     * Atajo de fila a la ficha de la mascota del registro (consulta, cirugía, prueba…); solo si el
     * usuario puede verla. Para tablas de modelos con relación `pet`.
     */
    public static function viewPetAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('viewPet')
            ->label('Ver mascota')
            ->icon('heroicon-o-identification')
            ->visible(fn (Model $record): bool => $record->pet && static::canView($record->pet))
            ->url(fn (Model $record): string => static::getUrl('view', ['record' => $record->pet_id]));
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\VaccinationsRelationManager::class,
            RelationManagers\ConsultationsRelationManager::class,
            RelationManagers\SurgeriesRelationManager::class,
            RelationManagers\TestsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPets::route('/'),
            'create' => Pages\CreatePet::route('/create'),
            'view' => Pages\ViewPet::route('/{record}'),
            'edit' => Pages\EditPet::route('/{record}/edit'),
        ];
    }
}
