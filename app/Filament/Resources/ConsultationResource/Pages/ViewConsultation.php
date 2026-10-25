<?php

namespace App\Filament\Resources\ConsultationResource\Pages;

use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\PetResource;
use App\Filament\Resources\UserResource;
use App\Models\Consultation;
use App\Services\PdfGeneratorService;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ViewConsultation extends ViewRecord
{
    protected static string $resource = ConsultationResource::class;

    public function getTitle(): string|Htmlable
    {
        return "Consulta de {$this->record->pet->name} · {$this->record->consultation_date->format('d/m/Y')}";
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadConsultation')
                ->label('Descargar informe')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->action(fn (PdfGeneratorService $pdfGenerator) => $pdfGenerator->download(
                    'pdf.consultation',
                    ['consultation' => $this->record],
                    "Consulta-{$this->record->pet->name}-{$this->record->id}.pdf",
                ))
                ->requiresConfirmation()
                ->modalHeading('Generar informe de consulta')
                ->modalDescription('¿Desea descargar el informe en PDF de esta consulta?'),
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
                        TextEntry::make('pet.name')
                            ->label('Mascota')
                            ->url(fn (Consultation $record) => PetResource::canView($record->pet)
                                ? PetResource::getUrl('view', ['record' => $record->pet_id])
                                : null),
                        TextEntry::make('user.name')
                            ->label('Veterinario')
                            ->url(fn (Consultation $record) => auth()->user()?->can('view_user')
                                ? UserResource::getUrl('view', ['record' => $record->user_id])
                                : null),
                        TextEntry::make('consultation_date')->label('Fecha de consulta')->date(),
                        TextEntry::make('created_at')->label('Registrado el')->dateTime(),
                        TextEntry::make('updated_at')->label('Última edición')->dateTime(),
                    ]),

                Section::make('Consulta')
                    ->schema([
                        TextEntry::make('anamnesis')
                            ->label('Anamnesis')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (?string $state) => filled($state) ? new HtmlString(nl2br(e($state))) : null),
                        TextEntry::make('diagnosis')
                            ->label('Diagnóstico')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (?string $state) => filled($state) ? new HtmlString(nl2br(e($state))) : null),
                        TextEntry::make('treatment')
                            ->label('Tratamiento')
                            ->columnSpanFull()
                            ->formatStateUsing(fn (?string $state) => filled($state) ? new HtmlString(nl2br(e($state))) : null),
                        TextEntry::make('observation')
                            ->label('Observación')
                            ->columnSpanFull()
                            ->placeholder('—')
                            ->formatStateUsing(fn (?string $state) => filled($state) ? new HtmlString(nl2br(e($state))) : null),
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
