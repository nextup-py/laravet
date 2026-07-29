<?php

namespace App\Filament\Resources\ConsultationResource\Pages;

use App\Filament\Resources\ConsultationResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewConsultation extends ViewRecord
{
    protected static string $resource = ConsultationResource::class;

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
                        TextEntry::make('user.name')->label('Veterinario'),
                    ]),

                Section::make('Consulta')
                    ->schema([
                        TextEntry::make('anamnesis')->label('Anamnesis')->columnSpanFull(),
                        TextEntry::make('diagnosis')->label('Diagnóstico')->columnSpanFull(),
                        TextEntry::make('treatment')->label('Tratamiento')->columnSpanFull(),
                        TextEntry::make('observation')->label('Observación')->columnSpanFull()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
