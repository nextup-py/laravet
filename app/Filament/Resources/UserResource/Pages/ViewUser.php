<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

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
                Section::make('Datos personales')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('name')->label('Nombre'),
                        TextEntry::make('email')->label('Correo electrónico')->icon('heroicon-m-envelope')->copyable(),
                        TextEntry::make('roles.name')->label('Rol')->badge()
                            ->formatStateUsing(fn (?string $state): string => UserResource::roleOptions()[$state] ?? $state ?? '—'),
                    ]),

                Section::make('Actividad clínica')
                    ->description('Registros cargados por este usuario.')
                    ->columns(5)
                    ->schema([
                        TextEntry::make('consultations_count')->label('Consultas')
                            ->state(fn ($record): int => $record->consultations()->count())->badge(),
                        TextEntry::make('surgeries_count')->label('Cirugías')
                            ->state(fn ($record): int => $record->surgeries()->count())->badge(),
                        TextEntry::make('vaccinations_count')->label('Vacunaciones')
                            ->state(fn ($record): int => $record->vaccinations()->count())->badge(),
                        TextEntry::make('tests_count')->label('Pruebas')
                            ->state(fn ($record): int => $record->tests()->count())->badge(),
                        TextEntry::make('pets_count')->label('Mascotas')
                            ->state(fn ($record): int => $record->pets()->count())->badge(),
                    ]),

                Section::make('Datos del domicilio')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('department.name')->label('Departamento'),
                        TextEntry::make('city.name')->label('Ciudad'),
                        TextEntry::make('neighborhood.name')->label('Barrio'),
                        TextEntry::make('address')->label('Dirección')->columnSpanFull(),
                    ]),

                Section::make('Registro')
                    ->columns(2)
                    ->schema([
                        TextEntry::make('created_at')->label('Registrado el')->dateTime('d/m/Y H:i'),
                        TextEntry::make('updated_at')->label('Actualizado el')->dateTime('d/m/Y H:i'),
                    ]),
            ]);
    }
}
