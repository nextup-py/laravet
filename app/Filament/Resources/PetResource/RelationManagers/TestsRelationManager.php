<?php

namespace App\Filament\Resources\PetResource\RelationManagers;

use App\Filament\Resources\TestResource;
use App\Models\Test;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class TestsRelationManager extends RelationManager
{
    protected static string $relationship = 'tests';

    protected static ?string $modelLabel = 'prueba laboratorial';

    protected static ?string $title = 'Pruebas Laboratoriales';

    public function form(Form $form): Form
    {
        return $form->schema(TestResource::formFields());
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->numeric()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('date')
                    ->label('Fecha')
                    ->date('d/m/Y')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
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
            ->defaultSort('date', 'desc')
            ->filters([
                TestResource::statusFilter(),
                Tables\Filters\Filter::make('date')
                    ->label('Fecha')
                    ->form([
                        Forms\Components\DatePicker::make('from')->label('Desde')->native(false),
                        Forms\Components\DatePicker::make('until')->label('Hasta')->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn (Builder $query, $date) => $query->whereDate('date', '>=', $date))
                            ->when($data['until'], fn (Builder $query, $date) => $query->whereDate('date', '<=', $date));
                    }),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->mutateFormDataUsing(function (array $data): array {
                        $data['user_id'] = Auth::id();

                        return $data;
                    }),
            ])
            ->recordAction('view')
            ->actions([
                Tables\Actions\ViewAction::make()->extraAttributes(['class' => 'hidden']),
                TestResource::uploadResultAction(),
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('openRecord')
                        ->label('Abrir ficha')
                        ->icon('heroicon-m-arrow-top-right-on-square')
                        ->visible(fn (Test $record): bool => TestResource::canView($record))
                        ->url(fn (Test $record): string => TestResource::getUrl('view', ['record' => $record])),
                    Tables\Actions\EditAction::make()
                        ->extraModalFooterActions([
                            Tables\Actions\DeleteAction::make()
                                ->cancelParentActions(),
                        ]),
                ]),
            ]);
    }
}
