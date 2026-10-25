<?php

namespace App\Filament\Resources;

use App\Enums\PetSpecies;
use App\Enums\VaccinationStatus;
use App\Filament\Resources\VaccinationResource\Pages;
use App\Models\Vaccination;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Gestión de vacunaciones aplicadas a las mascotas.
 */
class VaccinationResource extends Resource
{
    protected static ?string $model = Vaccination::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'vacunación';

    protected static ?string $pluralModelLabel = 'vacunaciones';

    protected static ?string $recordTitleAttribute = 'vaccine';

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getModel()::actionable()->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return static::getModel()::current()->overdue()->exists() ? 'danger' : 'warning';
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? static::getGlobalSearchResultTitle($record) : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['vaccine', 'batch', 'pet.name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('pet');
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->vaccine} · {$record->pet->name}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Próxima aplicación' => Carbon::parse($record->next_application)->format('d/m/Y'),
            'Estado' => $record->status->getLabel(),
        ];
    }

    public static function vaccineOptions(): array
    {
        return [
            'Séptuple' => 'Séptuple',
            'Rabia' => 'Rabia',
            'Moquillo' => 'Moquillo',
            'Parvovirus' => 'Parvovirus',
            'Adenovirus' => 'Adenovirus',
            'Leptospirosis' => 'Leptospirosis',
            'Parainfluenza' => 'Parainfluenza',
            'Bordetella' => 'Bordetella',
            'Leucemia Felina' => 'Leucemia Felina',
            'Panleucopenia' => 'Panleucopenia',
            'Calicivirus' => 'Calicivirus',
            'Rinotraqueítis Felina' => 'Rinotraqueítis Felina',
            'Triple Felina' => 'Triple Felina',
            'Lyme' => 'Lyme',
            'Gripe Canina' => 'Gripe Canina',
            'Tos de las Perreras' => 'Tos de las Perreras',
            'Coronavirus Canino' => 'Coronavirus Canino',
            'Giardia' => 'Giardia',
            'Rabia Recombinante' => 'Rabia Recombinante',
            'Antirrábica' => 'Antirrábica',
            'Herpesvirus Equino' => 'Herpesvirus Equino',
            'Mixomatosis' => 'Mixomatosis',
            'Enfermedad Hemorrágica Vírica' => 'Enfermedad Hemorrágica Vírica',
            'Vacuna Polivalente' => 'Vacuna Polivalente',
        ];
    }

    public static function manufacturerOptions(): array
    {
        $manufacturers = [
            'Zoetis', 'MSD', 'Elanco', 'Boehringer Ingelheim', 'Merial', 'Virbac', 'Ceva', 'Heska',
            'Bayer', 'Vetoquinol', 'Phibro', 'Hipra', 'Biogénesis Bagó', 'Bioiberica', 'Syva',
            'IDT Biologika', 'VECOL', 'Karnov', 'Labiana',
        ];

        return array_combine($manufacturers, $manufacturers);
    }

    /**
     * Campos del formulario, compartidos con la VaccinationsRelationManager de Pet
     * (que no incluye el selector de mascota porque ya está fijada por contexto).
     */
    public static function formFields(): array
    {
        return [
            Forms\Components\Select::make('vaccine')
                ->label('Vacuna')
                ->searchable()
                ->preload()
                ->options(self::vaccineOptions())
                ->default(fn () => request()->query('vaccine'))
                ->required(),
            Forms\Components\DatePicker::make('application_date')
                ->label('Fecha de aplicación')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->closeOnDateSelection()
                ->maxDate(now())
                ->live()
                ->afterStateUpdated(function (Get $get, Set $set, ?string $state, ?string $old, string $operation) {
                    if ($operation !== 'create' || blank($state)) {
                        return;
                    }

                    $previousSuggestion = filled($old) ? Carbon::parse($old)->addYear()->toDateString() : null;
                    $current = $get('next_application');

                    if (blank($current) || Carbon::parse($current)->toDateString() === $previousSuggestion) {
                        $set('next_application', Carbon::parse($state)->addYear()->toDateString());
                    }
                }),
            Forms\Components\DatePicker::make('next_application')
                ->label('Próxima aplicación')
                ->helperText('Se sugiere un año después de la aplicación; puede modificarla.')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->closeOnDateSelection()
                ->minDate(fn (string $operation) => $operation === 'create' ? today() : null)
                ->afterOrEqual('application_date'),
            Forms\Components\TextInput::make('batch')
                ->label('Lote')
                ->string()
                ->required(),
            Forms\Components\Select::make('manufacturer')
                ->label('Fabricante')
                ->searchable()
                ->preload()
                ->options(self::manufacturerOptions())
                ->required(),
            Forms\Components\Textarea::make('observation')
                ->label('Observación')
                ->autosize()
                ->columnSpanFull(),
        ];
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('pet_id')
                    ->label('Mascota')
                    ->relationship('pet', 'name', modifyQueryUsing: fn (Builder $query, ?Vaccination $record) => $query->where(
                        fn (Builder $q) => $q->where('active', true)
                            ->when($record?->pet_id, fn (Builder $q, $petId) => $q->orWhere('id', $petId))
                    ))
                    ->default(fn () => request()->query('pet_id'))
                    ->searchable(['name', 'id'])
                    ->preload()
                    ->required(),
                ...self::formFields(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withRenewedFlag())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->description(fn (Vaccination $record): ?string => OwnerResource::canViewAny() ? $record->pet->owner?->full_name : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vaccine')
                    ->label('Vacuna')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('application_date')
                    ->label('Fecha de aplicación')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_application')
                    ->label('Próxima aplicación')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\IconColumn::make('reminder_sent_at')
                    ->label('Recordatorio')
                    ->boolean()
                    ->getStateUsing(fn (Vaccination $record): bool => filled($record->reminder_sent_at))
                    ->tooltip(fn (Vaccination $record): string => filled($record->reminder_sent_at)
                        ? 'Enviado el '.Carbon::parse($record->reminder_sent_at)->format('d/m/Y H:i')
                        : 'Aún no enviado'),
                Tables\Columns\TextColumn::make('batch')
                    ->label('Lote')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Usuario')
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
            ->defaultSort('application_date', 'desc')
            ->filters([
                self::statusFilter(),
                Tables\Filters\SelectFilter::make('vaccine')
                    ->label('Vacuna')
                    ->options(self::vaccineOptions())
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('manufacturer')
                    ->label('Fabricante')
                    ->options(self::manufacturerOptions())
                    ->multiple()
                    ->searchable(),
                Tables\Filters\SelectFilter::make('pet_id')
                    ->label('Mascota')
                    ->relationship('pet', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\SelectFilter::make('species')
                    ->label('Especie')
                    ->options(PetSpecies::class)
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['values'] ?? null,
                        fn (Builder $q, array $values) => $q->whereHas('pet', fn (Builder $p) => $p->whereIn('species', $values))
                    )),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\ViewAction::make(),
                    Tables\Actions\EditAction::make(),
                    self::revaccinateAction(),
                ]),
            ])->selectCurrentPageOnly();
    }

    /**
     * Acción de fila que abre el alta de la misma vacuna para la misma mascota; compartida
     * con la VaccinationsRelationManager de Pet.
     */
    public static function revaccinateAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('revaccinate')
            ->label('Revacunar')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (): bool => static::canCreate())
            ->url(fn (Vaccination $record): string => static::getUrl('create', [
                'pet_id' => $record->pet_id,
                'vaccine' => $record->vaccine,
            ]));
    }

    /**
     * Filtro único por estado de vencimiento, compartido con la VaccinationsRelationManager de Pet.
     */
    public static function statusFilter(): Tables\Filters\SelectFilter
    {
        return Tables\Filters\SelectFilter::make('status')
            ->label('Estado')
            ->options(VaccinationStatus::class)
            ->query(fn (Builder $query, array $data): Builder => $query->when(
                $data['value'] ?? null,
                fn (Builder $q, string $value) => $q->withStatus(VaccinationStatus::from($value))
            ));
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
            'index' => Pages\ListVaccinations::route('/'),
            'create' => Pages\CreateVaccination::route('/create'),
            'view' => Pages\ViewVaccination::route('/{record}'),
            'edit' => Pages\EditVaccination::route('/{record}/edit'),
        ];
    }
}
