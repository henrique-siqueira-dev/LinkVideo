<?php

namespace App\Support;

class Bytes
{
    /** Formata bytes em pt-BR (ex.: "1,5 GB") sem depender da extensão intl. */
    public static function human(int|float $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return number_format($bytes, $i === 0 ? 0 : $precision, ',', '.').' '.$units[$i];
    }

    /** Converte valores do php.ini ("40M", "2G") para bytes. */
    public static function fromIni(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
