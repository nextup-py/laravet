<?php

namespace App\Filament\Actions;

use Filament\Actions\Action;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Collection;
use Illuminate\Support\Number;
use League\Csv\Bom;
use League\Csv\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Acción «Exportar» de las listas: arma el CSV o Excel en la misma petición y lo descarga al confirmar.
 *
 * Se usa en lugar del `ExportAction` de Filament, que encola el trabajo y deja el archivo en la
 * campanita de notificaciones. Las columnas, el formato de cada valor y los datos del propietario
 * salen del `Exporter` de cada recurso (`app/Filament/Exporters`).
 */
final class DownloadExport
{
    /**
     * Tope de filas por exportación: se genera en la petición, así que no conviene dejarlo abierto.
     */
    public static int $maxRows = 5000;

    /**
     * @param  class-string<Exporter>  $exporter
     * @param  string  $filename  Nombre base del archivo, sin extensión (se agrega la fecha).
     */
    public static function make(string $exporter, string $filename): Action
    {
        return Action::make('export')
            ->label('Exportar')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->visible(fn (): bool => auth()->user()?->can('export_data') ?? false)
            ->modalHeading('Exportar')
            ->modalDescription('Se exporta lo que muestra la tabla con los filtros y la búsqueda actuales.')
            ->modalSubmitActionLabel('Descargar')
            ->form(fn (): array => [
                Forms\Components\Select::make('format')
                    ->label('Formato')
                    ->options(['xlsx' => 'Excel (.xlsx)', 'csv' => 'CSV (.csv)'])
                    ->default('xlsx')
                    ->selectablePlaceholder(false)
                    ->required(),
                Forms\Components\CheckboxList::make('columns')
                    ->label('Columnas')
                    ->options(self::columnOptions($exporter))
                    ->default(array_keys(self::columnOptions($exporter)))
                    ->columns(2)
                    ->bulkToggleable()
                    ->required(),
            ])
            ->action(function (array $data, ListRecords $livewire) use ($exporter, $filename): ?StreamedResponse {
                $query = $exporter::modifyQuery($livewire->getTableQueryForExport());

                $total = $query->toBase()->getCountForPagination();

                if ($total > self::$maxRows) {
                    Notification::make()
                        ->title('Hay demasiadas filas para exportar')
                        ->body('La tabla tiene '.Number::format($total).' filas y el máximo es '.Number::format(self::$maxRows).'. Filtra la lista y vuelve a intentarlo.')
                        ->warning()
                        ->send();

                    return null;
                }

                $columnMap = collect(self::columnOptions($exporter))
                    ->only($data['columns'])
                    ->all();

                $rows = self::rows($exporter, $columnMap, $query->get());
                $name = $filename.'-'.now()->format('Y-m-d').'.'.$data['format'];

                return $data['format'] === 'csv'
                    ? self::csv($name, array_values($columnMap), $rows)
                    : self::xlsx($name, array_values($columnMap), $rows);
            });
    }

    /**
     * @param  class-string<Exporter>  $exporter
     * @return array<string, string> nombre de columna => etiqueta
     */
    private static function columnOptions(string $exporter): array
    {
        return collect($exporter::getColumns())
            ->mapWithKeys(fn (ExportColumn $column): array => [$column->getName() => $column->getLabel()])
            ->all();
    }

    /**
     * @param  class-string<Exporter>  $exporter
     * @param  array<string, string>  $columnMap
     * @return array<int, array<int, mixed>>
     */
    private static function rows(string $exporter, array $columnMap, Collection $records): array
    {
        $instance = new $exporter(new Export, $columnMap, []);

        return $records->map(fn ($record): array => $instance($record))->all();
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private static function csv(string $name, array $headers, array $rows): StreamedResponse
    {
        $csv = Writer::createFromFileObject(new \SplTempFileObject);
        $csv->setOutputBOM(Bom::Utf8); // para que Excel lea bien las tildes
        $csv->insertOne($headers);
        $csv->insertAll($rows);

        return response()->streamDownload(fn () => print ($csv->toString()), $name, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    private static function xlsx(string $name, array $headers, array $rows): StreamedResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'export');

        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->addRow(Row::fromValues($headers));

        foreach ($rows as $row) {
            $writer->addRow(Row::fromValues($row));
        }

        $writer->close();

        return response()->streamDownload(function () use ($path): void {
            readfile($path);
            @unlink($path);
        }, $name, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }
}
