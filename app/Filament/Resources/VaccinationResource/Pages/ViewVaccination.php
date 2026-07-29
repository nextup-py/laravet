<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Filament\Resources\VaccinationResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewVaccination extends ViewRecord
{
    protected static string $resource = VaccinationResource::class;

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
                Section::make('Información general')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pet.name')->label('Mascota'),
                        TextEntry::make('vaccine')->label('Vacuna'),
                        TextEntry::make('application_date')->label('Fecha de aplicación')->date(),
                        TextEntry::make('next_application')->label('Próxima aplicación')->date(),
                        TextEntry::make('batch')->label('Lote'),
                        TextEntry::make('manufacturer')->label('Fabricante'),
                        TextEntry::make('observation')->label('Observación')->columnSpanFull(),
                    ]),
            ]);
    }
}
