<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Filament\Resources\TestResource;
use App\Services\PdfGeneratorService;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Storage;

class ViewTest extends ViewRecord
{
    protected static string $resource = TestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadReport')
                ->label('Descargar reporte')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn (PdfGeneratorService $pdfGenerator) => $pdfGenerator->download(
                    'pdf.test-report',
                    ['test' => $this->record],
                    "Reporte-Prueba-{$this->record->pet->name}-{$this->record->id}.pdf",
                ))
                ->requiresConfirmation()
                ->modalHeading('Generar reporte de prueba laboratorial')
                ->modalDescription('¿Desea descargar el reporte en PDF de esta prueba?'),
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
                        TextEntry::make('result')
                            ->label('Resultados')
                            ->placeholder('—')
                            ->html()
                            ->listWithLineBreaks()
                            ->columnSpanFull()
                            ->formatStateUsing(fn (string $state): string => sprintf(
                                '<a href="%s" target="_blank" rel="noopener noreferrer" class="underline text-primary-600">%s</a>',
                                e(Storage::disk('public')->url($state)),
                                e(basename($state)),
                            )),
                        TextEntry::make('observation')->label('Observación')->columnSpanFull()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
