<?php

namespace App\Filament\Resources\CityResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class NeighborhoodsRelationManager extends RelationManager
{
    protected static string $relationship = 'neighborhoods';
    protected static ?string $modelLabel = 'barrio';
    protected static ?string $title = 'Barrios';

    public function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function canEditAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function canDeleteAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->translateLabel()
                    ->autofocus()
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
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->translateLabel()
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make(),
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
