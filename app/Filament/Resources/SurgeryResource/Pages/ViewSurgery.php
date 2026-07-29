<?php

namespace App\Filament\Resources\SurgeryResource\Pages;

use App\Filament\Resources\SurgeryResource;
use App\Services\PdfGeneratorService;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewSurgery extends ViewRecord
{
    protected static string $resource = SurgeryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadCertificate')
                ->label('Descargar certificado')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn (PdfGeneratorService $pdfGenerator) => $pdfGenerator->download(
                    'pdf.surgery-certificate',
                    ['surgery' => $this->record],
                    "Certificado-Cirugia-{$this->record->pet->name}-{$this->record->id}.pdf",
                ))
                ->requiresConfirmation()
                ->modalHeading('Generar certificado de cirugía')
                ->modalDescription('¿Desea descargar el certificado en PDF de esta cirugía?'),
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
                        TextEntry::make('date')->label('Fecha')->date('d/m/Y'),
                        TextEntry::make('type')->label('Tipo'),
                        TextEntry::make('observation')->label('Observación')->columnSpanFull()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
