<?php

namespace App\Filament\Resources\CityResource\Pages;

use App\Filament\Resources\CityResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewCity extends ViewRecord
{
    protected static string $resource = CityResource::class;

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
                Section::make('Datos de la ciudad')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('department.name')->label('Departamento'),
                        TextEntry::make('population')->label('Población')
                            ->numeric(thousandsSeparator: '.', decimalSeparator: ',')
                            ->placeholder('—'),
                        TextEntry::make('neighborhoods_count')->label('Barrios')
                            ->state(fn ($record): int => $record->neighborhoods()->count())->badge(),
                        TextEntry::make('owners_count')->label('Propietarios')
                            ->state(fn ($record): int => $record->owners()->count())->badge(),
                        TextEntry::make('users_count')->label('Usuarios')
                            ->state(fn ($record): int => $record->users()->count())->badge(),
                    ]),
            ]);
    }
}
