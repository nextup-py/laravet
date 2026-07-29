<?php

namespace App\Filament\Resources\PetResource\Pages;

use App\Filament\Resources\PetResource;
use App\Services\PdfGeneratorService;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewPet extends ViewRecord
{
    protected static string $resource = PetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadClinicalHistory')
                ->label('Descargar historia clínica')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn (PdfGeneratorService $pdfGenerator) => $pdfGenerator->download(
                    'pdf.clinical-history',
                    ['pet' => $this->record],
                    "Historia-Clinica-{$this->record->name}.pdf",
                ))
                ->requiresConfirmation()
                ->modalHeading('Generar historia clínica')
                ->modalDescription('¿Desea descargar el PDF con el historial completo de consultas?'),
            Actions\Action::make('downloadPetIdCard')
                ->label('Descargar ficha de mascota')
                ->icon('heroicon-o-identification')
                ->color('success')
                ->action(fn (PdfGeneratorService $pdfGenerator) => $pdfGenerator->download(
                    'pdf.pet-id-card',
                    ['pet' => $this->record],
                    "Ficha-Mascota-{$this->record->name}.pdf",
                ))
                ->requiresConfirmation()
                ->modalHeading('Generar ficha de identificación')
                ->modalDescription('¿Desea descargar el PDF con la ficha de identificación de la mascota?'),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Datos identificatorios')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('species')->label('Especie')->badge(),
                        TextEntry::make('breed')->label('Raza'),
                        TextEntry::make('age')->label('Edad'),
                        TextEntry::make('gender')->label('Género')->badge(),
                        TextEntry::make('reproduction')->label('Reproducción')->badge(),
                        TextEntry::make('active')->label('Activo')->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('owner.first_name')->label('Propietario'),
                    ]),

                Section::make('Más información')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('size')->label('Tamaño')->badge(),
                        TextEntry::make('weight')->label('Peso')->suffix(' kg'),
                        TextEntry::make('fur')->label('Pelaje'),
                        ImageEntry::make('image')->label('Imagen')->columnSpanFull(),
                    ]),
            ]);
    }
}
