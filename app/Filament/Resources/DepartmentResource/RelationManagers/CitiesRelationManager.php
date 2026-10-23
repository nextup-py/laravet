<?php

namespace App\Filament\Resources\DepartmentResource\RelationManagers;

use App\Filament\Concerns\GuardsDeletion;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Validation\Rules\Unique;

class CitiesRelationManager extends RelationManager
{
    protected static string $relationship = 'cities';

    protected static ?string $modelLabel = 'ciudad';

    protected static ?string $title = 'Ciudades';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->unique(
                        ignoreRecord: true,
                        modifyRuleUsing: fn (Unique $rule): Unique => $rule->where('department_id', $this->getOwnerRecord()->getKey()),
                    )
                    ->validationMessages(['unique' => 'Ya existe una ciudad con ese nombre en el departamento.'])
                    ->required(),
                Forms\Components\TextInput::make('population')
                    ->label('Población')
                    ->helperText('Opcional: déjela vacía si se desconoce.')
                    ->integer()
                    ->minValue(1),
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
                Tables\Columns\TextColumn::make('population')
                    ->label('Población')
                    ->numeric(thousandsSeparator: '.', decimalSeparator: ',')
                    ->placeholder('—')
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
