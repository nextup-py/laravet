<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Filament\Resources\PetResource;
use App\Filament\Resources\TestResource;
use App\Filament\Resources\UserResource;
use App\Services\PdfGeneratorService;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewTest extends ViewRecord
{
    protected static string $resource = TestResource::class;

    public function getTitle(): string|Htmlable
    {
        return "{$this->record->type} · {$this->record->pet->name}";
    }

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
                        TextEntry::make('pet.name')->label('Mascota')
                            ->url(fn ($record): ?string => PetResource::canView($record->pet)
                                ? PetResource::getUrl('view', ['record' => $record->pet_id])
                                : null),
                        TextEntry::make('type')->label('Tipo'),
                        TextEntry::make('date')->label('Fecha')->date('d/m/Y'),
                        TextEntry::make('status')->label('Estado')->badge(),
                        TextEntry::make('user.name')->label('Veterinario')
                            ->url(fn ($record): ?string => auth()->user()?->can('view_user')
                                ? UserResource::getUrl('view', ['record' => $record->user_id])
                                : null),
                        TextEntry::make('created_at')->label('Registrada el')->dateTime('d/m/Y H:i'),
                        TextEntry::make('result')
                            ->label('Resultados')
                            ->placeholder('Pendiente de carga')
                            ->html()
                            ->listWithLineBreaks()
                            ->columnSpanFull()
                            ->state(fn ($record): array => collect(array_values($record->result ?? []))
                                ->filter(fn ($path): bool => is_string($path))
                                ->map(fn (string $path, int $index): string => sprintf(
                                    '<a href="%s" target="_blank" rel="noopener noreferrer" class="underline text-primary-600">%s</a>',
                                    e(route('filament.admin.tests.result', ['test' => $record, 'index' => $index])),
                                    e(basename($path)),
                                ))
                                ->all()),
                        TextEntry::make('observation')->label('Observación')->columnSpanFull()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
