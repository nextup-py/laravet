<?php

namespace App\Reports;

/**
 * Resultado de un reporte listo para mostrar en pantalla o descargar.
 */
final class ReportResult
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public function __construct(
        public readonly string $title,
        public readonly array $headers,
        public readonly array $rows,
        public readonly bool $includesOwnerData = false,
        public readonly ?string $subtitle = null,
    ) {}
}
