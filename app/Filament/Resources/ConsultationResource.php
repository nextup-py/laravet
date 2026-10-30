<?php

namespace App\Filament\Resources;

use App\Enums\AiUrgencyLevel;
use App\Enums\AiUsageStatus;
use App\Enums\PetSpecies;
use App\Filament\Concerns\OpensViewFirst;
use App\Filament\Resources\ConsultationResource\Pages;
use App\Models\Consultation;
use App\Models\Pet;
use App\Services\AIDiagnosticService;
use Filament\Forms;
use Filament\Forms\Components\Section;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Gestión de consultas veterinarias, con diagnóstico asistido por IA.
 */
class ConsultationResource extends Resource
{
    use OpensViewFirst;

    protected static ?string $model = Consultation::class;

    protected static ?string $slug = 'consultas';

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'consulta';

    protected static ?string $recordTitleAttribute = 'diagnosis';

    public static function getRecordTitle(?Model $record): string|Htmlable|null
    {
        return $record ? static::getGlobalSearchResultTitle($record) : null;
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['diagnosis', 'anamnesis', 'pet.name'];
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('pet');
    }

    public static function getGlobalSearchResultTitle(Model $record): string
    {
        return "Consulta de {$record->pet->name} · {$record->consultation_date->format('d/m/Y')}";
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return ['Diagnóstico' => str($record->diagnosis)->limit(60)->toString()];
    }

    /**
     * Resumen clínico de la mascota seleccionada y sus últimas consultas, para tener contexto al redactar.
     */
    protected static function petContext(?Pet $pet, ?Consultation $current): HtmlString
    {
        if (! $pet) {
            return new HtmlString('');
        }

        $header = e($pet->summaryLine());

        $previous = $pet->consultations()
            ->when($current?->exists, fn (Builder $q) => $q->whereKeyNot($current->getKey()))
            ->latest('consultation_date')
            ->limit(3)
            ->get()
            ->map(fn (Consultation $c): string => '<li>'.e($c->consultation_date->format('d/m/Y').': '.str($c->diagnosis)->limit(80)).'</li>')
            ->implode('');

        $list = $previous !== ''
            ? '<p class="mt-2 font-medium">Últimas consultas</p><ul class="list-disc pl-5">'.$previous.'</ul>'
            : '<p class="mt-2">Sin consultas previas registradas.</p>';

        return new HtmlString('<div class="text-sm text-gray-600 dark:text-gray-300"><p class="font-medium">'.$header.'</p>'.$list.'</div>');
    }

    protected static function aiSuggestOverwritesExisting(Get $get): bool
    {
        return filled($get('diagnosis')) || filled($get('treatment'));
    }

    protected static function aiEditedHelperText(Get $get): string
    {
        $consultation = new Consultation([
            'diagnosis' => $get('diagnosis'),
            'treatment' => $get('treatment'),
            'ai_diagnosis_suggestion' => $get('ai_diagnosis_suggestion'),
            'ai_treatment_suggestion' => $get('ai_treatment_suggestion'),
            'ai_suggested_at' => $get('ai_suggested_at'),
        ]);

        return $consultation->aiUsageStatus() === AiUsageStatus::Edited
            ? '✏️ Editado respecto a la sugerencia de la IA. Máximo 5000 caracteres.'
            : 'Máximo 5000 caracteres.';
    }

    protected static function aiUrgencyBadge(?string $urgency): HtmlString
    {
        $labels = [
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
            'emergencia' => 'Emergencia',
        ];

        $classes = [
            'baja' => 'bg-gray-100 text-gray-700 dark:bg-gray-500/20 dark:text-gray-300',
            'media' => 'bg-info-100 text-info-700 dark:bg-info-500/20 dark:text-info-300',
            'alta' => 'bg-warning-100 text-warning-700 dark:bg-warning-500/20 dark:text-warning-300',
            'emergencia' => 'bg-danger-100 text-danger-700 dark:bg-danger-500/20 dark:text-danger-300',
        ];

        $label = $labels[$urgency] ?? $urgency;
        $class = $classes[$urgency] ?? $classes['baja'];

        return new HtmlString(
            '<span class="inline-flex items-center rounded-full px-2 py-1 text-xs font-medium '.$class.'">'
            .'Urgencia sugerida por la IA: '.e($label)
            .'</span>'
        );
    }

    protected static function aiErrorMessage(\RuntimeException $e): string
    {
        $message = $e->getMessage();

        if (str_contains($message, 'ANTHROPIC_API_KEY')) {
            return 'La integración con IA no está configurada. Contacta al administrador del sistema.';
        }

        if (str_contains($message, 'formato esperado')) {
            return 'La IA devolvió una respuesta inesperada. Intenta nuevamente.';
        }

        if (str_contains($message, 'conectar con la API')) {
            return 'No se pudo conectar con el servicio de IA. Verifica tu conexión e intenta nuevamente.';
        }

        return 'No se pudo generar la sugerencia. Intenta nuevamente en unos minutos.';
    }

    /**
     * Peso y temperatura del paciente en la consulta (opcionales); los comparte el RelationManager de la mascota.
     *
     * @return array<int, Forms\Components\Component>
     */
    public static function vitalsFields(): array
    {
        return [
            Forms\Components\TextInput::make('weight')
                ->label('Peso')
                ->suffix('kg')
                ->numeric()
                ->inputMode('decimal')
                ->minValue(0.1)
                ->maxValue(150),
            Forms\Components\TextInput::make('temperature')
                ->label('Temperatura')
                ->suffix('°C')
                ->numeric()
                ->inputMode('decimal')
                ->step(0.1)
                ->minValue(30)
                ->maxValue(45),
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
                            ->relationship('pet', 'name', modifyQueryUsing: fn (Builder $query, ?Consultation $record) => $query->where(
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
                            ->content(fn (Get $get, ?Consultation $record) => static::petContext(Pet::find($get('pet_id')), $record)),
                        Forms\Components\DatePicker::make('consultation_date')
                            ->label('Fecha de consulta')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->default(now()),
                    ]),
                Section::make('Signos vitales')
                    ->description('Opcional: peso y temperatura del paciente durante la consulta.')
                    ->columns(2)
                    ->schema(self::vitalsFields()),
                Section::make('Consulta')
                    ->schema([
                        Forms\Components\Textarea::make('anamnesis')
                            ->label('Anamnesis')
                            ->columnSpanFull()
                            ->autosize()
                            ->maxLength(5000)
                            ->helperText('Máximo 5000 caracteres.')
                            ->required(),
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('aiSuggest')
                                ->label('Asistir con IA')
                                ->modalHeading(fn (Get $get) => static::aiSuggestOverwritesExisting($get) ? 'Sobrescribir sugerencia existente' : null)
                                ->modalDescription(fn (Get $get) => static::aiSuggestOverwritesExisting($get) ? 'Ya hay contenido en Diagnóstico o Tratamiento. ¿Quieres reemplazarlo con la sugerencia de la IA?' : null)
                                ->modalSubmitActionLabel(fn (Get $get) => static::aiSuggestOverwritesExisting($get) ? 'Sí, sobrescribir' : null)
                                ->requiresConfirmation(fn (Get $get) => static::aiSuggestOverwritesExisting($get))
                                ->icon('heroicon-o-sparkles')
                                ->color('info')
                                ->action(function (Get $get, Set $set) {
                                    try {
                                        $pet = Pet::find($get('pet_id'));

                                        if (! $pet) {
                                            Notification::make()
                                                ->title('Selecciona una mascota primero')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        if (blank($get('anamnesis'))) {
                                            Notification::make()
                                                ->title('Completa la anamnesis primero')
                                                ->body('La IA necesita la anamnesis para poder sugerir un diagnóstico.')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        $result = app(AIDiagnosticService::class)->suggest($pet, $get('anamnesis'));
                                        $set('diagnosis', $result['diagnosis']);
                                        $set('treatment', $result['treatment']);
                                        $set('ai_diagnosis_suggestion', $result['diagnosis']);
                                        $set('ai_treatment_suggestion', $result['treatment']);
                                        $set('ai_urgency', $result['urgency']);
                                        $set('ai_suggested_at', now());
                                        $set('ai_input_tokens', $result['input_tokens']);
                                        $set('ai_output_tokens', $result['output_tokens']);

                                        if (in_array($result['urgency'], ['alta', 'emergencia'], true)) {
                                            Notification::make()
                                                ->title('Posible urgencia detectada')
                                                ->body('La IA marcó esta consulta con urgencia "'.$result['urgency'].'". Prioriza la revisión del paciente.')
                                                ->danger()
                                                ->send();
                                        }
                                    } catch (\RuntimeException $e) {
                                        Log::error('Error en sugerencia de IA: '.$e->getMessage());

                                        Notification::make()
                                            ->title('Error al generar sugerencia')
                                            ->body(static::aiErrorMessage($e))
                                            ->danger()
                                            ->send();
                                    } catch (\Throwable $e) {
                                        Log::error('Error inesperado en sugerencia de IA: '.$e->getMessage());

                                        Notification::make()
                                            ->title('Error al generar sugerencia')
                                            ->body('Ocurrió un error inesperado. Intenta nuevamente en unos minutos.')
                                            ->danger()
                                            ->send();
                                    }
                                })
                                ->hidden(fn () => ! auth()->user()?->can('use_ai_diagnostics')),
                        ])->columnSpanFull(),
                        Forms\Components\Placeholder::make('aiHelp')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->hidden(fn () => ! auth()->user()?->can('use_ai_diagnostics'))
                            ->content(new HtmlString(
                                '<p class="text-sm text-gray-500 dark:text-gray-400">'
                                .'La IA sugiere un diagnóstico y tratamiento en base a la anamnesis. '
                                .'Revisa siempre la sugerencia antes de guardar — no reemplaza tu criterio profesional.'
                                .'</p>'
                                .'<p wire:loading wire:target="mountFormComponentAction" '
                                .'class="text-sm font-medium text-primary-600 dark:text-primary-400 mt-1">'
                                .'Generando sugerencia con IA… esto puede tardar unos segundos.'
                                .'</p>'
                            )),
                        Forms\Components\Placeholder::make('aiUrgencyBadge')
                            ->hiddenLabel()
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => filled($get('ai_urgency')))
                            ->content(fn (Get $get) => static::aiUrgencyBadge($get('ai_urgency'))),
                        Forms\Components\Hidden::make('ai_diagnosis_suggestion'),
                        Forms\Components\Hidden::make('ai_treatment_suggestion'),
                        Forms\Components\Hidden::make('ai_urgency'),
                        Forms\Components\Hidden::make('ai_suggested_at'),
                        Forms\Components\Hidden::make('ai_input_tokens'),
                        Forms\Components\Hidden::make('ai_output_tokens'),
                        Forms\Components\Textarea::make('diagnosis')
                            ->label('Diagnóstico')
                            ->columnSpanFull()
                            ->autosize()
                            ->maxLength(5000)
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get) => static::aiEditedHelperText($get))
                            ->required(),
                        Forms\Components\Textarea::make('treatment')
                            ->label('Tratamiento')
                            ->columnSpanFull()
                            ->autosize()
                            ->maxLength(5000)
                            ->live(onBlur: true)
                            ->helperText(fn (Get $get) => static::aiEditedHelperText($get))
                            ->required(),
                        Forms\Components\Textarea::make('observation')
                            ->label('Observación')
                            ->columnSpanFull()
                            ->autosize()
                            ->maxLength(5000)
                            ->helperText('Máximo 5000 caracteres.'),
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
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->description(fn (Consultation $record): ?string => OwnerResource::canViewAny() ? $record->pet->owner?->full_name : null)
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('consultation_date')
                    ->label('Fecha de consulta')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('diagnosis')
                    ->label('Diagnóstico')
                    ->searchable()
                    ->sortable()
                    ->limit(60)
                    ->tooltip(fn (Tables\Columns\TextColumn $column): ?string => strlen((string) $column->getState()) > 60 ? $column->getState() : null),
                Tables\Columns\IconColumn::make('ai_suggested_at')
                    ->label('IA')
                    ->boolean()
                    ->getStateUsing(fn (Consultation $record) => filled($record->ai_suggested_at)),
                Tables\Columns\TextColumn::make('ai_urgency')
                    ->label('Urgencia IA')
                    ->badge()
                    ->placeholder('—'),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Veterinario')
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
            ->defaultSort('consultation_date', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('species')
                    ->label('Especie')
                    ->options(PetSpecies::class)
                    ->multiple()
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['values'] ?? null,
                        fn (Builder $q, array $values) => $q->whereHas('pet', fn (Builder $p) => $p->whereIn('species', $values))
                    )),
                Tables\Filters\SelectFilter::make('ai_urgency')
                    ->label('Urgencia IA')
                    ->options(AiUrgencyLevel::class)
                    ->multiple(),
                Tables\Filters\SelectFilter::make('ai_usage')
                    ->label('Uso de IA')
                    ->options(AiUsageStatus::class)
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        AiUsageStatus::Manual->value => $query->whereNull('ai_suggested_at'),
                        AiUsageStatus::UsedAsIs->value => $query->whereNotNull('ai_suggested_at')
                            ->whereColumn('diagnosis', 'ai_diagnosis_suggestion')
                            ->whereColumn('treatment', 'ai_treatment_suggestion'),
                        AiUsageStatus::Edited->value => $query->whereNotNull('ai_suggested_at')
                            ->where(fn (Builder $q) => $q->whereColumn('diagnosis', '!=', 'ai_diagnosis_suggestion')
                                ->orWhereColumn('treatment', '!=', 'ai_treatment_suggestion')),
                        default => $query,
                    }),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('Veterinario')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Tables\Filters\Filter::make('consultation_date')
                    ->label('Fecha de consulta')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde')->native(false),
                        Forms\Components\DatePicker::make('until')->label('Hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('consultation_date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('consultation_date', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\EditAction::make(),
                    Tables\Actions\Action::make('openReport')
                        ->label('Informe PDF')
                        ->icon('heroicon-o-document-text')
                        ->authorize(fn (Consultation $record): bool => static::canView($record))
                        ->url(fn (Consultation $record): string => route('filament.admin.pdf.consultation', $record))
                        ->openUrlInNewTab(),
                    Tables\Actions\Action::make('followUp')
                        ->label('Nueva consulta de control')
                        ->icon('heroicon-o-arrow-path')
                        ->visible(fn (Consultation $record): bool => $record->pet->active && static::canCreate())
                        ->url(fn (Consultation $record): string => static::getUrl('create', ['pet_id' => $record->pet_id])),
                    PetResource::viewPetAction(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConsultations::route('/'),
            'create' => Pages\CreateConsultation::route('/create'),
            'view' => Pages\ViewConsultation::route('/{record}'),
            'edit' => Pages\EditConsultation::route('/{record}/edit'),
        ];
    }
}
