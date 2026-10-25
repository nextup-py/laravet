<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Enums\PetSpecies;
use App\Filament\Resources\PetResource;
use App\Models\Pet;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

/**
 * Muestra, en modo solo lectura, las mascotas registradas por este usuario.
 */
class PetsRelationManager extends RelationManager
{
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
                    ->circular()
                    ->defaultImageUrl(fn (Pet $record): string => $record->initials_avatar_url),
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('species')
                    ->label('Especie')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('breed')
                    ->label('Raza')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('species')
                    ->label('Especie')
                    ->options(PetSpecies::class)
                    ->multiple(),
            ])
            ->recordUrl(fn (Pet $record): ?string => PetResource::canView($record)
                ? PetResource::getUrl('view', ['record' => $record])
                : null);
    }
}
