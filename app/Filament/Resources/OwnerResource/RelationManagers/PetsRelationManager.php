<?php

namespace App\Filament\Resources\OwnerResource\RelationManagers;

use App\Enums\PetSpecies;
use App\Filament\Resources\PetResource;
use App\Models\Pet;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Lista, en modo solo lectura, las mascotas del propietario. Crear y editar se hace en
 * PetResource (el botón "Registrar mascota" de la ficha del propietario abre el formulario con `?owner_id=`).
 */
class PetsRelationManager extends RelationManager
{
    protected static string $relationship = 'pets';

    protected static ?string $modelLabel = 'mascota';

    protected static ?string $title = 'Mascotas';

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
                    ->numeric()
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
                Tables\Columns\TextColumn::make('age')
                    ->label('Edad')
                    ->getStateUsing(fn (Pet $record) => $record->age)
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query
                        ->orderBy('birthdate', $direction === 'asc' ? 'desc' : 'asc')),
                Tables\Columns\IconColumn::make('active')
                    ->label('Activo')
                    ->boolean()
                    ->sortable(),
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
            ->defaultSort('name')
            ->filters([
                Tables\Filters\SelectFilter::make('species')
                    ->label('Especie')
                    ->options(PetSpecies::class)
                    ->multiple(),
                Tables\Filters\TernaryFilter::make('active')
                    ->label('Estado')
                    ->placeholder('Todas')
                    ->trueLabel('Activas')
                    ->falseLabel('Inactivas'),
            ])
            ->recordUrl(fn (Pet $record): ?string => PetResource::canView($record)
                ? PetResource::getUrl('view', ['record' => $record])
                : null)
            ->actions([
                Tables\Actions\ActionGroup::make([
                    Tables\Actions\Action::make('view')
                        ->label('Ver')
                        ->icon('heroicon-m-eye')
                        ->visible(fn (Pet $record): bool => PetResource::canView($record))
                        ->url(fn (Pet $record): string => PetResource::getUrl('view', ['record' => $record])),
                    Tables\Actions\Action::make('edit')
                        ->label('Editar')
                        ->icon('heroicon-m-pencil-square')
                        ->visible(fn (Pet $record): bool => PetResource::canEdit($record))
                        ->url(fn (Pet $record): string => PetResource::getUrl('edit', ['record' => $record])),
                ]),
            ]);
    }
}
