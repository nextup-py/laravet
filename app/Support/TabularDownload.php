<?php

namespace App\Support;

use League\Csv\Bom;
use League\Csv\Writer;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Descargas tabulares (CSV y Excel) armadas en la misma petición, compartidas
 * por las exportaciones de listas y por los reportes.
 */
final class TabularDownload
{
    /**
     * Evita la inyección de fórmulas (CSV injection): los textos que empiezan por =, +, -, @, tab o
     * retorno de carro se prefijan con una comilla simple para que Excel/Sheets no los evalúen.
     * Los exporters de listas ya lo hacen por columna; los reportes lo aplican a todas las celdas.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @return array<int, array<int, mixed>>
     */
    public static function protectFormulas(array $rows): array
    {
        return array_map(
            fn (array $row): array => array_map(
                fn (mixed $value): mixed => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value,
                $row,
            ),
            $rows,
        );
    }

    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, mixed>>  $rows
     */
    public static function csv(string $name, array $headers, array $rows): StreamedResponse
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
    public static function xlsx(string $name, array $headers, array $rows): StreamedResponse
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
