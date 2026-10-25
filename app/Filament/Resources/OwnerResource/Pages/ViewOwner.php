<?php

namespace App\Filament\Resources\OwnerResource\Pages;

use App\Filament\Resources\OwnerResource;
use App\Filament\Resources\PetResource;
use Filament\Actions;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewOwner extends ViewRecord
{
    protected static string $resource = OwnerResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->full_name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('registerPet')
                ->label('Registrar mascota')
                ->icon('heroicon-o-plus')
                ->color('success')
                ->visible(fn (): bool => PetResource::canCreate())
                ->url(fn (): string => PetResource::getUrl('create', ['owner_id' => $this->record->getKey()])),
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Datos personales')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('ci')->label('CI')
                            ->formatStateUsing(fn ($state): string => number_format((int) $state, 0, ',', '.')),
                        TextEntry::make('first_name')->label('Nombre(s)'),
                        TextEntry::make('last_name')->label('Apellido(s)'),
                        TextEntry::make('gender')->label('Género')->badge(),
                        TextEntry::make('email')->label('Correo electrónico')->icon('heroicon-m-envelope')->copyable(),
                        TextEntry::make('phone')->label('Teléfono')
                            ->icon('heroicon-m-chat-bubble-left-ellipsis')
                            ->url(fn ($record): string => $record->whatsapp_url, shouldOpenInNewTab: true),
                    ]),

                Section::make('Datos del domicilio')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('department.name')->label('Departamento'),
                        TextEntry::make('city.name')->label('Ciudad'),
                        TextEntry::make('neighborhood.name')->label('Barrio'),
                        TextEntry::make('address')->label('Dirección'),
                    ]),

                Section::make('Mascotas')
                    ->visible(fn (): bool => PetResource::canViewAny())
                    ->schema([
                        RepeatableEntry::make('pets')
                            ->label('Mascotas')
                            ->hiddenLabel()
                            ->contained(false)
                            ->grid(3)
                            ->placeholder('Este propietario aún no tiene mascotas registradas.')
                            ->schema([
                                ImageEntry::make('image')->label('Imagen')->hiddenLabel()->circular()->height(64),
                                TextEntry::make('name')->label('Nombre')->hiddenLabel()->weight('bold')
                                    ->url(fn ($record): ?string => PetResource::canView($record)
                                        ? PetResource::getUrl('view', ['record' => $record])
                                        : null),
                                TextEntry::make('species')->label('Especie')->hiddenLabel()->badge(),
                                TextEntry::make('breed')->label('Raza')->hiddenLabel()->color('gray'),
                            ]),
                    ]),
            ]);
    }
}
