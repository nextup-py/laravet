<?php

namespace App\Filament\Resources\PetResource\Pages;

use App\Filament\Actions\DownloadExport;
use App\Filament\Exporters\PetExporter;
use App\Filament\Resources\PetResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListPets extends ListRecords
{
    protected static string $resource = PetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadExport::make(PetExporter::class, 'mascotas', 'Mascotas'),
            Actions\CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        return [
            'Todos' => Tab::make(),
            'Activos' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('active', true)),
            'Inactivos' => Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->where('active', false)),
        ];
    }
}
