<?php

namespace App\Filament\Resources\NeighborhoodResource\Pages;

use App\Filament\Resources\NeighborhoodResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewNeighborhood extends ViewRecord
{
    protected static string $resource = NeighborhoodResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

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
                Section::make('Datos del barrio')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('city.name')->label('Ciudad'),
                        TextEntry::make('city.department.name')->label('Departamento'),
                        TextEntry::make('owners_count')->label('Propietarios')
                            ->state(fn ($record): int => $record->owners()->count())->badge(),
                        TextEntry::make('users_count')->label('Usuarios')
                            ->state(fn ($record): int => $record->users()->count())->badge(),
                    ]),
            ]);
    }
}
