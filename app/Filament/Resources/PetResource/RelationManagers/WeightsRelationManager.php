<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

use App\Enums\PetWeightSource;
use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\PetResource;
use App\Models\PetWeight;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Historial de pesos de la mascota. Los pesajes que vienen de una consulta se corrigen en la consulta;
 * los demás se pueden editar o eliminar (menos el último que queda, para que la mascota siempre tenga historial).
 */
class WeightsRelationManager extends RelationManager
{
    protected static string $relationship = 'weights';

    protected static ?string $modelLabel = 'pesaje';

    protected static ?string $title = 'Pesos';

    protected function canCreate(): bool
    {
        return PetResource::canEdit($this->getOwnerRecord());
    }

    protected function canEdit(Model $record): bool
    {
        return $record->consultation_id === null && PetResource::canEdit($this->getOwnerRecord());
    }

    protected function canDelete(Model $record): bool
    {
        return $this->canEdit($record) && $this->getOwnerRecord()->weights()->count() > 1;
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\DatePicker::make('weighed_at')
                ->label('Fecha del pesaje')
                ->native(false)
                ->displayFormat('d/m/Y')
                ->default(today())
                ->maxDate(today())
                ->required(),
            Forms\Components\TextInput::make('weight')
                ->label('Peso')
                ->suffix('kg')
                ->numeric()
                ->inputMode('decimal')
                ->minValue(0.1)
                ->maxValue(150)
                ->required(),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('weighed_at')
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('weighed_at')->orderByDesc('id'))
            ->columns([
                Tables\Columns\TextColumn::make('weighed_at')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('weight')
                    ->label('Peso')
                    ->suffix(' kg')
                    ->numeric(decimalPlaces: 2, decimalSeparator: ',', thousandsSeparator: '.')
                    ->sortable(),
                Tables\Columns\TextColumn::make('source')
                    ->label('Origen')
                    ->badge(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Registrado por')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('source')
                    ->label('Origen')
                    ->options(PetWeightSource::class),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Registrar peso')
                    ->modalWidth('md')
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();
                        $data['source'] = PetWeightSource::Weighing;

                        return $data;
                    }),
            ])
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('openConsultation')
                        ->label('Abrir consulta')
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->visible(fn (PetWeight $record): bool => $record->consultation_id !== null && ConsultationResource::canView($record->consultation))
                        ->url(fn (PetWeight $record): string => ConsultationResource::getUrl('view', ['record' => $record->consultation_id])),
                    Tables\Actions\EditAction::make()
                        ->modalWidth('md')
                        ->extraModalFooterActions([
                            Tables\Actions\DeleteAction::make()
                                ->cancelParentActions(),
                        ]),
                ]),
            ]);
    }
}
