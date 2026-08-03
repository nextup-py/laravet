<?php

namespace App\Filament\Resources\ConsultationResource\Pages;

use App\Filament\Resources\ConsultationResource;
use App\Models\Consultation;
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
                        TextEntry::make('consultation_date')->label('Fecha de consulta')->date(),
                    ]),

                Section::make('Consulta')
                    ->schema([
                        TextEntry::make('anamnesis')->label('Anamnesis')->columnSpanFull(),
                        TextEntry::make('diagnosis')->label('Diagnóstico')->columnSpanFull(),
                        TextEntry::make('treatment')->label('Tratamiento')->columnSpanFull(),
                        TextEntry::make('observation')->label('Observación')->columnSpanFull()
                            ->placeholder('—'),
                    ]),

                Section::make('Diagnóstico asistido por IA')
                    ->columns(2)
                    ->visible(fn (Consultation $record) => filled($record->ai_suggested_at))
                    ->schema([
                        TextEntry::make('ai_usage_status')
                            ->label('Estado')
                            ->state(fn (Consultation $record) => $record->aiUsageStatus())
                            ->badge(),
                        TextEntry::make('ai_urgency')
                            ->label('Urgencia detectada')
                            ->badge()
                            ->placeholder('—'),
                        TextEntry::make('ai_suggested_at')
                            ->label('Sugerido el')
                            ->dateTime(),
                        TextEntry::make('ai_input_tokens')
                            ->label('Tokens (entrada)')
                            ->placeholder('—'),
                        TextEntry::make('ai_output_tokens')
                            ->label('Tokens (salida)')
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
