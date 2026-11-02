<?php

namespace App\Filament\Resources\PetResource\Pages;

use App\Filament\Actions\RegisterWeight;
use App\Filament\Resources\ConsultationResource;
use App\Filament\Resources\OwnerResource;
use App\Filament\Resources\PetResource;
use App\Filament\Resources\VaccinationResource;
use App\Models\Pet;
use App\Models\PetWeight;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPet extends ViewRecord
{
    protected static string $resource = PetResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\ActionGroup::make([
                Actions\Action::make('openClinicalHistory')
                    ->label('Historia clínica')
                    ->icon('heroicon-o-document-text')
                    ->visible(fn (): bool => auth()->user()?->can('view_any_consultation') ?? false)
                    ->url(fn (): string => route('filament.admin.pdf.clinical-history', $this->record))
                    ->openUrlInNewTab(),
                Actions\Action::make('openPetIdCard')
                    ->label('Ficha de la mascota')
                    ->icon('heroicon-o-identification')
                    ->url(fn (): string => route('filament.admin.pdf.pet-id-card', $this->record))
                    ->openUrlInNewTab(),
                Actions\Action::make('openVaccineCard')
                    ->label('Carnet de vacunación')
                    ->icon('heroicon-o-shield-check')
                    ->visible(fn (): bool => auth()->user()?->can('view_any_vaccination') ?? false)
                    ->url(fn (): string => route('filament.admin.pdf.vaccine-card', $this->record))
                    ->openUrlInNewTab(),
            ])
                ->label('Documentos PDF')
                ->icon('heroicon-o-document-arrow-down')
                ->color('success')
                ->button(),
            RegisterWeight::forPage(),
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
                        TextEntry::make('owner.first_name')->label('Propietario')
                            ->formatStateUsing(fn ($record): string => OwnerResource::canView($record->owner)
                                ? $record->owner->full_name
                                : $record->owner->first_name)
                            ->url(fn ($record): ?string => OwnerResource::canView($record->owner)
                                ? OwnerResource::getUrl('view', ['record' => $record->owner_id])
                                : null),
                        TextEntry::make('owner.phone')->label('Teléfono del propietario')
                            ->visible(fn ($record): bool => OwnerResource::canView($record->owner))
                            ->icon('heroicon-m-chat-bubble-left-ellipsis')
                            ->url(fn ($record): string => $record->owner->whatsapp_url, shouldOpenInNewTab: true),                    ]),

                Section::make('Más información')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('size')->label('Tamaño')->badge(),
                        TextEntry::make('weight')->label('Peso')->suffix(' kg'),
                        TextEntry::make('fur')->label('Pelaje'),
                        ImageEntry::make('image')
                            ->label('Imagen')
                            ->defaultImageUrl(fn (Pet $record): string => $record->initials_avatar_url)
                            ->columnSpanFull(),
                    ]),

                Section::make('Seguimiento')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('last_consultation')
                            ->label('Última consulta')
                            ->state(fn (Pet $record): ?string => $record->consultations()->max('consultation_date'))
                            ->date('d/m/Y')
                            ->placeholder('Sin consultas')
                            ->visible(fn (): bool => ConsultationResource::canViewAny()),
                        TextEntry::make('next_vaccine')
                            ->label('Próxima vacuna')
                            ->state(fn (Pet $record): ?string => $record->vaccinations()->current()->whereNotNull('next_application')->min('next_application'))
                            ->date('d/m/Y')
                            ->placeholder('—')
                            ->visible(fn (): bool => VaccinationResource::canViewAny()),
                        TextEntry::make('weight_history')
                            ->label('Últimos pesajes')
                            ->state(fn (Pet $record): array => $record->weights()
                                ->orderByDesc('weighed_at')->orderByDesc('id')->limit(5)->get()
                                ->map(fn (PetWeight $weight): string => $weight->weighed_at->format('d/m/Y').' · '.rtrim(rtrim(number_format((float) $weight->weight, 2, ',', ''), '0'), ',').' kg')
                                ->all())
                            ->listWithLineBreaks()
                            ->placeholder('—'),
                    ]),
            ]);
    }
}
