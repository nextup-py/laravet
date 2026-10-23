<?php

namespace App\Filament\Resources\DepartmentResource\Pages;

use App\Filament\Resources\DepartmentResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewDepartment extends ViewRecord
{
    protected static string $resource = DepartmentResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

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
                Section::make('Datos del departamento')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('capital')->label('Capital'),
                        TextEntry::make('cities_count')->label('Ciudades')
                            ->state(fn ($record): int => $record->cities()->count())->badge(),
                        TextEntry::make('owners_count')->label('Propietarios')
                            ->state(fn ($record): int => $record->owners()->count())->badge(),
                        TextEntry::make('users_count')->label('Usuarios')
                            ->state(fn ($record): int => $record->users()->count())->badge(),
                    ]),
            ]);
    }
}
