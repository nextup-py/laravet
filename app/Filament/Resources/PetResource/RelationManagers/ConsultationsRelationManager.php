<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

use App\Filament\Concerns\ClinicRoles;
use App\Filament\Concerns\HasClinicRelationManagerAuthorization;
use App\Models\Consultation;
use App\Services\AIDiagnosticService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConsultationsRelationManager extends RelationManager
{
    use HasClinicRelationManagerAuthorization;

    protected static string $relationship = 'consultations';

    protected static ?string $modelLabel = 'consulta';

    protected static ?string $title = 'Consultas';

    protected function createRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    protected function editRoles(): array
    {
        return [ClinicRoles::ADMIN, ClinicRoles::VETERINARIAN];
    }

    protected function aiSuggestOverwritesExisting(Get $get): bool
    {
        return filled($get('diagnosis')) || filled($get('treatment'));
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
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
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->hidden(fn () => ! auth()->user()?->hasAnyRole(['admin', 'veterinarian']))
                        ->modalHeading(fn (Get $get) => $this->aiSuggestOverwritesExisting($get) ? 'Sobrescribir sugerencia existente' : null)
                        ->modalDescription(fn (Get $get) => $this->aiSuggestOverwritesExisting($get) ? 'Ya hay contenido en Diagnóstico o Tratamiento. ¿Querés reemplazarlo con la sugerencia de la IA?' : null)
                        ->modalSubmitActionLabel(fn (Get $get) => $this->aiSuggestOverwritesExisting($get) ? 'Sí, sobrescribir' : null)
                        ->requiresConfirmation(fn (Get $get) => $this->aiSuggestOverwritesExisting($get))
                        ->action(function (Get $get, Set $set, $livewire) {
                            try {
                                $pet = $livewire->getOwnerRecord();
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
                        }),
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

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric(),
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
                    ->label('Usuario')
                    ->searchable()
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
                Tables\Filters\Filter::make('created_at')
                    ->label('Creado a las')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde')->native(false),
                        Forms\Components\DatePicker::make('until')->label('Hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        return $data;
                    }),
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
}
