<?php

namespace App\Filament\Resources;

use App\Enums\PetSpecies;
use App\Enums\TestStatus;
use App\Filament\Concerns\FocusesOnOwnRecords;
use App\Filament\Concerns\OpensViewFirst;
use App\Filament\Resources\TestResource\Pages;
use App\Http\Controllers\TestResultController;
use App\Models\Pet;
use App\Models\Test;
use App\Settings\ClinicSettings;
use Carbon\Carbon;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/**
 * Gestión de pruebas laboratoriales realizadas a las mascotas.
 */
class TestResource extends Resource
{
    use FocusesOnOwnRecords, OpensViewFirst;

    protected static ?string $model = Test::class;

    protected static ?string $slug = 'pruebas';

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-beaker';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'prueba laboratorial';

    protected static ?string $pluralModelLabel = 'pruebas laboratoriales';

    protected static ?string $recordTitleAttribute = 'type';

    /** Pruebas sin resultado cargado (las del veterinario, si solo ve las suyas). */
    public static function getNavigationBadge(): ?string
    {
        $count = static::scopeToOwnRecords(static::getModel()::withoutResult())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Pruebas sin resultado';
    }

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? static::getGlobalSearchResultTitle($record) : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['type', 'observation', 'pet.name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('pet');
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "{$record->type} · {$record->pet->name}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return [
            'Fecha' => Carbon::parse($record->date)->format('d/m/Y'),
            'Estado' => $record->status->getLabel(),
        ];
    }

    public static function typeOptions(): array
    {
        return ClinicSettings::catalog('test_types');
    }

    /**
     * Archivos de resultado: se guardan en un disco privado y solo se sirven
     * a usuarios con permiso (ver TestResultController).
     */
    public static function resultUpload(): Forms\Components\FileUpload
    {
        return Forms\Components\FileUpload::make('result')
            ->label('Resultados')
            ->helperText('Opcional: puede registrar la prueba ahora y cargar el resultado después. Formatos: PDF, PNG o JPG.')
            ->disk('test-results')
            ->directory('resultados')
            ->visibility('private')
            ->multiple()
            ->maxParallelUploads(1)
            ->panelLayout('grid')
            ->openable()
            ->downloadable()
            ->uploadingMessage('Subiendo archivo adjunto...')
            ->maxFiles(5)
            ->acceptedFileTypes(TestResultController::INLINE_MIME_TYPES)
            ->columnSpanFull();
    }

    /**
     * Resumen clínico de la mascota seleccionada y sus pruebas previas, para tener contexto al registrar.
     */
    protected static function petContext(?Pet $pet, ?Test $current): HtmlString
    {
        if (! $pet) {
            return new HtmlString('');
        }

        $previous = $pet->tests()
            ->when($current?->exists, fn (Builder $q) => $q->whereKeyNot($current->getKey()))
            ->latest('date')
            ->limit(3)
            ->get()
            ->map(fn (Test $t): string => '<li>'.e(Carbon::parse($t->date)->format('d/m/Y').': '.$t->type).'</li>')
            ->implode('');

        $list = $previous !== ''
            ? '<p class="mt-2 font-medium">Pruebas previas</p><ul class="list-disc pl-5">'.$previous.'</ul>'
            : '<p class="mt-2">Sin pruebas previas registradas.</p>';

        return new HtmlString('<div class="text-sm text-gray-600 dark:text-gray-300"><p class="font-medium">'.e($pet->summaryLine()).'</p>'.$list.'</div>');
    }

    /**
     * Campos del formulario, compartidos con la TestsRelationManager de Pet
     * (que no incluye el selector de mascota porque ya está fijada por contexto).
     */
    public static function formFields(): array
    {
        return [
            Forms\Components\DatePicker::make('date')
                ->label('Fecha')
                ->required()
                ->native(false)
                ->displayFormat('d/m/Y')
                ->closeOnDateSelection()
                ->maxDate(now()),
            Forms\Components\Select::make('type')
                ->label('Tipo')
                ->searchable()
                ->preload()
                ->options(self::typeOptions())
                ->required(),
            self::resultUpload(),
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
                Section::make('Información general')
                    ->columns(2)
                    ->schema([
                        Forms\Components\Select::make('pet_id')
                            ->label('Mascota')
                            ->relationship('pet', 'name', modifyQueryUsing: fn (Builder $query, ?Test $record) => $query->where(
                                fn (Builder $q) => $q->where('active', true)
                                    ->when($record?->pet_id, fn (Builder $q, $petId) => $q->orWhere('id', $petId))
                            ))
                            ->default(fn () => request()->query('pet_id'))
                            ->searchable(['name', 'id'])
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\Placeholder::make('petContext')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => filled($get('pet_id')))
                            ->content(fn (Get $get, ?Test $record) => static::petContext(Pet::find($get('pet_id')), $record)),
                        ...self::formFields(),
                    ]),
            ]);
    }

    /**
     * Acción de fila para cargar el resultado de una prueba pendiente sin entrar a editarla.
     */
    public static function uploadResultAction(): Tables\Actions\Action
    {
        return Tables\Actions\Action::make('uploadResult')
            ->label('Cargar resultado')
            ->icon('heroicon-o-arrow-up-tray')
            ->visible(fn (Test $record): bool => $record->status === TestStatus::Pending
                && (auth()->user()?->can('update', $record) ?? false))
            ->form([self::resultUpload()->required()])
            ->action(function (Test $record, array $data): void {
                $record->update(['result' => array_values($data['result'])]);

                Notification::make()->title('Resultado cargado')->success()->send();
            });
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->description(fn (Test $record): ?string => OwnerResource::canViewAny() ? $record->pet->owner?->full_name : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('result')
                    ->label('Resultados')
                    ->getStateUsing(function (Test $record) {
                        $files = $record->result;

                        return is_array($files) && count($files) > 0 ? count($files).' archivo(s)' : '—';
                    }),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Veterinario')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('observation')
                    ->label('Observación')
                    ->limit(50)
                    ->tooltip(fn (Tables\Columns\TextColumn $column): ?string => strlen((string) $column->getState()) > 50 ? $column->getState() : null)
                    ->searchable()
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
            ->defaultSort('date', 'desc')
            ->filters([
                self::statusFilter(),
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(self::typeOptions())
                    ->multiple()
                    ->searchable(),
                Tables\Filters\Filter::make('date')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde')->native(false),
                        Forms\Components\DatePicker::make('until')->label('Hasta')->native(false),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query
                        ->when($data['from'], fn (Builder $q, $date) => $q->whereDate('date', '>=', $date))
                        ->when($data['until'], fn (Builder $q, $date) => $q->whereDate('date', '<=', $date))),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Veterinario')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
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
                    Tables\Actions\EditAction::make(),
                    self::uploadResultAction(),
                    Tables\Actions\Action::make('openReport')
                        ->label('Reporte PDF')
                        ->icon('heroicon-o-document-text')
                        ->authorize(fn (Test $record): bool => static::canView($record))
                        ->url(fn (Test $record): string => route('filament.admin.pdf.test', $record))
                        ->openUrlInNewTab(),
                    PetResource::viewPetAction(),
                ]),
            ]);
    }

    /**
     * Filtro por estado del resultado, compartido con la TestsRelationManager de Pet.
     */
    public static function statusFilter(): Tables\Filters\SelectFilter
    {
        return Tables\Filters\SelectFilter::make('status')
            ->label('Estado')
            ->options(TestStatus::class)
            ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                TestStatus::Pending->value => $query->withoutResult(),
                TestStatus::WithResult->value => $query->withResult(),
                default => $query,
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTests::route('/'),
            'create' => Pages\CreateTest::route('/create'),
            'view' => Pages\ViewTest::route('/{record}'),
            'edit' => Pages\EditTest::route('/{record}/edit'),
        ];
    }
}
