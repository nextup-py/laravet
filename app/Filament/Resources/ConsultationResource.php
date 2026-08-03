<?php

namespace App\Filament\Resources;

use App\Filament\Concerns\ClinicRoles;
use App\Filament\Concerns\HasClinicResourceAuthorization;
use App\Filament\Resources\ConsultationResource\Pages;
use App\Models\Consultation;
use App\Models\Pet;
use App\Services\AIDiagnosticService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Log;

/**
 * Gestión de consultas veterinarias, con diagnóstico asistido por IA.
 */
class ConsultationResource extends Resource
{
    use HasClinicResourceAuthorization;

    protected static ?string $model = Consultation::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $modelLabel = 'consulta';

    protected static function createRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    protected static function editRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    protected static function aiSuggestOverwritesExisting(Get $get): bool
    {
        return filled($get('diagnosis')) || filled($get('treatment'));
    }

    public static function form(Form $form): Form
    {
        return $form
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
                Forms\Components\Textarea::make('anamnesis')
                    ->label('Anamnesis')
                    ->columnSpanFull()
                    ->autosize()
                    ->maxLength(5000)
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
                            } catch (\Throwable $e) {
                                Log::error('Error en sugerencia de IA: '.$e->getMessage());

                                Notification::make()
                                    ->title('Error al generar sugerencia')
                                    ->body('No se pudo generar la sugerencia. Intentá nuevamente en unos minutos.')
                                    ->danger()
                                    ->send();
                            }
                        })
                        ->hidden(fn () => ! auth()->user()?->hasAnyRole(['admin', 'veterinarian'])),
                ])->columnSpanFull(),
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
                    ->required(),
                Forms\Components\Textarea::make('treatment')
                    ->label('Tratamiento')
                    ->columnSpanFull()
                    ->autosize()
                    ->maxLength(5000)
                    ->required(),
                Forms\Components\Textarea::make('observation')
                    ->label('Observación')
                    ->columnSpanFull()
                    ->autosize()
                    ->maxLength(5000),
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
                Tables\Columns\TextColumn::make('user.name')
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
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
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
