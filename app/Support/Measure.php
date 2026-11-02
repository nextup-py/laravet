<?php

namespace App\Support;

/**
 * Formato único de medidas para el personal: coma decimal y sin ceros sobrantes («8 kg», «6,2 kg», «39,1 °C»).
 */
final class Measure
{
    public static function number(float|int|string|null $value, int $maxDecimals = 2): string
    {
        if (! is_numeric($value)) {
            return '—';
        }

        $formatted = number_format((float) $value, $maxDecimals, ',', '.');

        return str_contains($formatted, ',') ? rtrim(rtrim($formatted, '0'), ',') : $formatted;
    }

    public static function kg(float|int|string|null $value): string
    {
        return is_numeric($value) ? self::number($value).' kg' : '—';
    }

    public static function celsius(float|int|string|null $value): string
    {
        return is_numeric($value) ? self::number($value, 1).' °C' : '—';
    }
}
