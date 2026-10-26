<?php

namespace App\Filament\Exporters\Concerns;

use Carbon\Carbon;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

/**
 * Textos y criterios comunes de los exportadores de listas.
 */
trait ExportsLabels
{
    public static function getCompletedNotificationBody(Export $export): string
    {
        $rows = Number::format($export->successful_rows);

        return "La exportación terminó: se exportaron {$rows} ".($export->successful_rows === 1 ? 'fila' : 'filas').'.';
    }

    /**
     * Columna de texto libre. Los valores que empiezan por =, +, -, @, tab o retorno de carro se
     * prefijan con una comilla simple para que Excel/Sheets no los evalúe como fórmula (CSV injection).
     */
    protected static function text(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)->label($label)->formatStateUsing(
            fn ($state) => is_string($state) && preg_match('/^[=+\-@\t\r]/', $state) ? "'".$state : $state
        );
    }

    /**
     * El export corre en la misma petición, sin depender del driver de cola del entorno. Con la cola
     * `database` el archivo se generaba, pero el aviso de «completada» se enviaba como notificación a
     * la base de datos, y el proyecto no tiene la tabla `notifications`: el trabajo fallaba y la
     * persona solo veía «Exportación iniciada». En sync Filament muestra el aviso con los botones
     * de descarga directamente en la respuesta.
     */
    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    protected static function formatDate(mixed $state): ?string
    {
        return filled($state) ? Carbon::parse($state)->format('d/m/Y') : null;
    }

    /**
     * Los datos del propietario solo se exportan a quien puede verlos (mismo criterio que las tablas).
     */
    protected static function canExportOwnerData(): bool
    {
        return auth()->user()?->can('view_any_owner') ?? false;
    }
}
