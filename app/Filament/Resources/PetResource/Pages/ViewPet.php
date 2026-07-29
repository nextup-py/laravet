<?php

namespace App\Filament\Resources\PetResource\Pages;

use App\Filament\Resources\PetResource;
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
            Actions\EditAction::make(),
        ];
    }

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make(__('ID information'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->translateLabel(),
                        TextEntry::make('species')->translateLabel()->badge(),
                        TextEntry::make('breed')->translateLabel(),
                        TextEntry::make('age')->label('Edad'),
                        TextEntry::make('gender')->translateLabel()->badge(),
                        TextEntry::make('reproduction')->translateLabel()->badge(),
                        TextEntry::make('active')->translateLabel()->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'Activo' : 'Inactivo')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('owner.first_name')->translateLabel()->label('Propietario'),
                    ]),

                Section::make(__('More information'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('size')->translateLabel()->badge(),
                        TextEntry::make('weight')->translateLabel()->suffix(' kg'),
                        TextEntry::make('fur')->label(__('Pelage')),
                        ImageEntry::make('image')->translateLabel()->columnSpanFull(),
                    ]),
            ]);
    }
}
