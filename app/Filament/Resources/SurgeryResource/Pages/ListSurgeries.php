<?php

namespace App\Filament\Resources\SurgeryResource\Pages;

use App\Enums\SurgeryStatus;
use App\Filament\Actions\DownloadExport;
use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Exporters\SurgeryExporter;
use App\Filament\Resources\SurgeryResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListSurgeries extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = SurgeryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadExport::make(SurgeryExporter::class, 'cirugias', 'Cirugías'),
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
        $scopes = [
            SurgeryStatus::Scheduled->value => 'scheduled',
            SurgeryStatus::Today->value => 'today',
            SurgeryStatus::Done->value => 'done',
        ];
        $tabs = ['Todos' => Tab::make()];

        foreach (SurgeryStatus::cases() as $status) {
            $scope = $scopes[$status->value];

            $tabs[$status->getLabel()] = Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->{$scope}())
                ->badge(fn (): int => SurgeryResource::getEloquentQuery()->{$scope}()->count())
                ->badgeColor($status->getColor());
        }

        return $tabs;
    }
}
