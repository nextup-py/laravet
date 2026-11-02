<?php

namespace App\Filament\Resources\TestResource\Pages;

use App\Enums\TestStatus;
use App\Filament\Actions\DownloadExport;
use App\Filament\Concerns\OpensViewOnRowClick;
use App\Filament\Exporters\TestExporter;
use App\Filament\Resources\TestResource;
use Filament\Actions;
use Filament\Resources\Components\Tab;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;

class ListTests extends ListRecords
{
    use OpensViewOnRowClick;

    protected static string $resource = TestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadExport::make(TestExporter::class, 'pruebas', 'Pruebas'),
            Actions\CreateAction::make(),
        ];
    }

    /**
     * Pendientes y con resultado, con la cantidad de registros; «Todos» abre sin filtros.
     *
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $scopes = [
            TestStatus::Pending->value => 'withoutResult',
            TestStatus::WithResult->value => 'withResult',
        ];
        $tabs = ['Todos' => Tab::make()];

        foreach (TestStatus::cases() as $status) {
            $scope = $scopes[$status->value];

            $tabs[$status->getLabel()] = Tab::make()
                ->modifyQueryUsing(fn (Builder $query) => $query->{$scope}())
                ->badge(fn (): int => TestResource::getEloquentQuery()->{$scope}()->count())
                ->badgeColor($status->getColor());
        }

        return $tabs;
    }
}
