<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

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
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('email')->label('Correo electrónico'),
                        TextEntry::make('roles.name')->label('Rol')->badge()
                            ->formatStateUsing(fn (?string $state): string => UserResource::roleOptions()[$state] ?? $state ?? '—'),
                    ]),

                Section::make('Datos del domicilio')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('department.name')->label('Departamento'),
                        TextEntry::make('city.name')->label('Ciudad'),
                        TextEntry::make('neighborhood.name')->label('Barrio'),
                        TextEntry::make('address')->label('Dirección')->columnSpanFull(),
                    ]),
            ]);
    }
}
