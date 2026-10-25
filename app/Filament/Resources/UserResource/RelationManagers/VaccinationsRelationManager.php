<?php

namespace App\Filament\Resources\UserResource\RelationManagers;

use App\Filament\Resources\VaccinationResource;
use App\Models\Vaccination;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Muestra, en modo solo lectura, las vacunaciones registradas por este usuario.
 */
class VaccinationsRelationManager extends RelationManager
{
    protected static string $relationship = 'vaccinations';

    protected static ?string $title = 'Vacunaciones registradas';

    public function form(Form $form): Form
    {
        return $form->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->withRenewedFlag())
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('pet.name')
                    ->label('Mascota')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('vaccine')
                    ->label('Vacuna')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('application_date')
                    ->label('Fecha de aplicación')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('next_application')
                    ->label('Próxima aplicación')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge(),
                Tables\Columns\TextColumn::make('batch')
                    ->label('Lote')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('manufacturer')
                    ->label('Fabricante')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('application_date', 'desc')
            ->filters([
                VaccinationResource::statusFilter(),
            ])
            ->recordUrl(fn (Vaccination $record): ?string => VaccinationResource::canView($record)
                ? VaccinationResource::getUrl('view', ['record' => $record])
                : null);
    }
}
