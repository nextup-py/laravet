<?php

namespace App\Filament\Resources\VaccinationResource\Pages;

use App\Enums\VaccinationStatus;
use App\Filament\Actions\DownloadExport;
use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Exporters\VaccinationExporter;
use App\Filament\Resources\VaccinationResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListVaccinations extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = VaccinationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadExport::make(VaccinationExporter::class, 'vacunaciones', 'Vacunaciones'),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Una pestaña por estado, con la cantidad de registros; «Todos» abre sin filtros.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $tabs = ['Todos' => Tab::make()];

        foreach (VaccinationStatus::cases() as $status) {
            $tabs[$status->getLabel()] = Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->withStatus($status))
                ->badge(fn (): int => VaccinationResource::getEloquentQuery()->withStatus($status)->count())
                ->badgeColor($status->getColor());
        }

        return $tabs;
    }
}
