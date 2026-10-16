<?php

namespace App\Support;

class Money
{
    /** Centavos -> "R$ 1.234,56". */
    public static function format(?int $cents): string
    {
        return 'R$ '.static::plain($cents);
    }

    /** Centavos -> "1.234,56" (valor de campo de formulário). */
    public static function plain(?int $cents): string
    {
        return number_format(($cents ?? 0) / 100, 2, ',', '.');
    }

    /** "R$ 1.234,56" / "1234,56" / "1234.56" -> centavos. Vazio -> null. */
    public static function parse(?string $value): ?int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $value = preg_replace('/[^\d,.]/', '', $value);

        // Último separador com 1 ou 2 dígitos depois é o decimal; os demais são milhar.
        if (preg_match('/^(.*)[,.](\d{1,2})$/', $value, $m)) {
            return (int) preg_replace('/\D/', '', $m[1]) * 100 + (int) str_pad($m[2], 2, '0');
        }

        return (int) preg_replace('/\D/', '', $value) * 100;
    }
}
