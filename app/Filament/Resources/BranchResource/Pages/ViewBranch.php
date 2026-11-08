<?php

namespace App\Filament\Resources\BranchResource\Pages;

use App\Filament\Resources\BranchResource;
use Filament\Actions;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewBranch extends ViewRecord
{
    protected static string $resource = BranchResource::class;

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
                Section::make('Datos de la sucursal')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('code')->label('Código de establecimiento')->badge(),
                        TextEntry::make('name')->label('Nombre'),
                        IconEntry::make('is_active')->label('Habilitada')->boolean(),
                        TextEntry::make('phone')->label('Teléfono')->placeholder('—'),
                        TextEntry::make('email')->label('Correo electrónico')->placeholder('—'),
                        TextEntry::make('users_count')->label('Personal')
                            ->state(fn ($record): int => $record->users()->count())->badge(),
                    ]),

                Section::make('Ubicación')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('department.name')->label('Departamento')->placeholder('—'),
                        TextEntry::make('city.name')->label('Ciudad')->placeholder('—'),
                        TextEntry::make('neighborhood.name')->label('Barrio')->placeholder('—'),
                        TextEntry::make('address')->label('Dirección')->placeholder('—')->columnSpanFull(),
                    ]),

                Section::make('Registro')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Registrada el')->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')->label('Actualizada el')->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
