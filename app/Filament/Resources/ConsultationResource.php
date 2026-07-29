<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConsultationResource\Pages;
use App\Models\Consultation;
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
use Illuminate\Database\Eloquent\Model;

class ConsultationResource extends Resource
{
    protected static ?string $model = Consultation::class;

    protected static ?string $navigationGroup = 'Historial clínico';

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $modelLabel = 'consulta';

    public static function form(Form $form): Form
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
                        ->action(function (Get $get, Set $set, $record) {
                            try {
                                $pet = $record?->pet;
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
                        })
                        ->hidden(fn ($record) => $record === null || ! auth()->user()?->hasAnyRole(['admin', 'veterinarian'])),
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
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
                /* Tables\Columns\TextColumn::make('anamnesis')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(), */
                Tables\Columns\TextColumn::make('diagnosis')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
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
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian', 'assistant']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public static function canDelete(Model $record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'veterinarian']) ?? false;
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageConsultations::route('/'),
        ];
    }
}
