<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Filament\Resources\TestResource;
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
