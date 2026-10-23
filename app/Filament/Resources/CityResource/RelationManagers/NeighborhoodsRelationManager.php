<?php

namespace App\Filament\Resources\CityResource\RelationManagers;

use App\Filament\Concerns\GuardsDeletion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class NeighborhoodsRelationManager extends RelationManager
{
    protected static string $relationship = 'neighborhoods';

    protected static ?string $modelLabel = 'barrio';

    protected static ?string $title = 'Barrios';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->autofocus()
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('city_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->validationMessages(['unique' => 'Ya existe un barrio con ese nombre en la ciudad.'])
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->numeric()
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->extraModalFooterActions([
                        GuardsDeletion::apply(Tables\Actions\DeleteAction::make()->cancelParentActions()),
                    ]),
            ]);
    }
}
