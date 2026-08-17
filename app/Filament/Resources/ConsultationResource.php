<?php

namespace App\Filament\Resources;

use App\Enums\AiUsageStatus;
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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;

/**
 * Gestión de consultas veterinarias, con diagnóstico asistido por IA.
 */
class ConsultationResource extends Resource
{
    protected static ?string $model = Consultation::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'consulta';

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
            return 'La integración con IA no está configurada. Contactá al administrador del sistema.';
        }

        if (str_contains($message, 'formato esperado')) {
            return 'La IA devolvió una respuesta inesperada. Intentá nuevamente.';
        }

        if (str_contains($message, 'conectar con la API')) {
            return 'No se pudo conectar con el servicio de IA. Verificá tu conexión e intentá nuevamente.';
        }

        return 'No se pudo generar la sugerencia. Intentá nuevamente en unos minutos.';
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
                            ->searchable(['name', 'id'])
                            ->preload()
                            ->live()
                            ->required(),
                        Forms\Components\DatePicker::make('consultation_date')
                            ->label('Fecha de consulta')
                            ->required()
                            ->native(false)
                            ->maxDate(now())
                            ->default(now()),
                    ]),
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
                                ->modalDescription(fn (Get $get) => static::aiSuggestOverwritesExisting($get) ? 'Ya hay contenido en Diagnóstico o Tratamiento. ¿Querés reemplazarlo con la sugerencia de la IA?' : null)
                                ->modalSubmitActionLabel(fn (Get $get) => static::aiSuggestOverwritesExisting($get) ? 'Sí, sobrescribir' : null)
                                ->requiresConfirmation(fn (Get $get) => static::aiSuggestOverwritesExisting($get))
                                ->icon('heroicon-o-sparkles')
                                ->color('info')
                                ->action(function (Get $get, Set $set) {
                                    try {
                                        $pet = Pet::find($get('pet_id'));

                                        if (! $pet) {
                                            Notification::make()
                                                ->title('Seleccioná una mascota primero')
                                                ->warning()
                                                ->send();

                                            return;
                                        }

                                        if (blank($get('anamnesis'))) {
                                            Notification::make()
                                                ->title('Completá la anamnesis primero')
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
                                                ->body('La IA marcó esta consulta con urgencia "'.$result['urgency'].'". Priorizá la revisión del paciente.')
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
                                            ->body('Ocurrió un error inesperado. Intentá nuevamente en unos minutos.')
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
                                .'Revisá siempre la sugerencia antes de guardar — no reemplaza tu criterio profesional.'
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
                    ->numeric(),
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('consultation_date')
                    ->label('Fecha de consulta')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('diagnosis')
                    ->label('Diagnóstico')
                    ->searchable()
                    ->sortable()
                    ->limit(60),
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
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('consultation_date', 'desc')
            ->filters([
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
