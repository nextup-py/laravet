<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

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

class ConsultationsRelationManager extends RelationManager
{
    protected static string $relationship = 'consultations';

    protected static ?string $modelLabel = 'consulta';

    protected static ?string $title = 'Consultas';

    public function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public function canEditAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public function canDeleteAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Textarea::make('anamnesis')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->autosize()
                    ->required(),
                Forms\Components\Actions::make([
                    Forms\Components\Actions\Action::make('aiSuggest')
                        ->label('Asistir con IA')
                        ->icon('heroicon-o-sparkles')
                        ->color('info')
                        ->hidden(fn () => ! auth()->user()?->hasAnyRole(['admin', 'veterinarian']))
                        ->action(function (Get $get, Set $set, $livewire) {
                            try {
                                $pet = $livewire->getOwnerRecord();
                                $result = app(AIDiagnosticService::class)->suggest($pet, $get('anamnesis'));
                                $set('diagnosis', $result['diagnosis']);
                                $set('treatment', $result['treatment']);
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Error al generar sugerencia')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                ])->columnSpanFull(),
                Forms\Components\Textarea::make('diagnosis')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->autosize()
                    ->required(),
                Forms\Components\Textarea::make('treatment')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->autosize()
                    ->required(),
                Forms\Components\Textarea::make('observation')
                    ->translateLabel()
                    ->columnSpanFull()
                    ->autosize(),
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
                Tables\Columns\TextColumn::make('diagnosis')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
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
                Tables\Filters\Filter::make('created_at')
                    ->translateLabel()
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
