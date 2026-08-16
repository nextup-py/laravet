<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Concerns\HasClinicRelationManagerAuthorization;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Muestra, en modo solo lectura, las mascotas registradas por este usuario.
 */
class PetsRelationManager extends RelationManager
{
    use HasClinicRelationManagerAuthorization;

    protected static string $relationship = 'pets';

    protected static ?string $title = 'Mascotas registradas';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image')
                    ->label('Imagen')
                    ->circular(),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('species')
                    ->label('Especie')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('breed')
                    ->label('Raza')
                    ->searchable()
                    ->sortable(),
            ]);
    }
}
