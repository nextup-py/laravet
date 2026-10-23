<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Filament\Resources\PetResource;
use App\Filament\Resources\VaccinationResource;
use Carbon\Carbon;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewVaccination extends ViewRecord
{
    protected static string $resource = VaccinationResource::class;

    public function getTitle(): string|Htmlable
    {
        return "{$this->record->vaccine} · {$this->record->pet->name}";
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
                Section::make('Información general')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('pet.name')->label('Mascota')
                            ->url(fn ($record): ?string => PetResource::canView($record->pet)
                                ? PetResource::getUrl('view', ['record' => $record->pet_id])
                                : null),
                        TextEntry::make('vaccine')->label('Vacuna'),
                        TextEntry::make('application_date')->label('Fecha de aplicación')->date('d/m/Y'),
                        TextEntry::make('next_application')->label('Próxima aplicación')->date('d/m/Y'),
                        TextEntry::make('status')->label('Estado')->badge(),
                        TextEntry::make('reminder_sent_at')->label('Recordatorio')
                            ->state(fn ($record): string => filled($record->reminder_sent_at)
                                ? 'Enviado el '.Carbon::parse($record->reminder_sent_at)->format('d/m/Y H:i')
                                : 'Aún no enviado'),
                        TextEntry::make('batch')->label('Lote'),
                        TextEntry::make('manufacturer')->label('Fabricante'),
                        TextEntry::make('observation')->label('Observación')->placeholder('—')->columnSpanFull(),
                    ]),
            ]);
    }
}
