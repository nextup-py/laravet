<?php

namespace App\Filament\Resources\OwnerResource\Pages;

use App\Filament\Resources\OwnerResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewOwner extends ViewRecord
{
    protected static string $resource = OwnerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Datos personales')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ci')->label('CI'),
                        TextEntry::make('first_name')->label('Nombre(s)'),
                        TextEntry::make('last_name')->label('Apellido(s)'),
                        TextEntry::make('gender')->label('Género')->badge()
                            ->formatStateUsing(fn (string $state): string => $state === 'Male' ? 'Masculino' : 'Femenino'),
                        TextEntry::make('email')->label('Correo electrónico'),
                        TextEntry::make('phone')->label('Teléfono'),
                    ]),

                Section::make('Datos del domicilio')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('department.name')->label('Departamento'),
                        TextEntry::make('city.name')->label('Ciudad'),
                        TextEntry::make('neighborhood.name')->label('Barrio'),
                        TextEntry::make('address')->label('Dirección'),
                    ]),
            ]);
    }
}
