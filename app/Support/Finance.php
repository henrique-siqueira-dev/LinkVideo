<?php

namespace App\Support;

use App\Models\Setting;

/**
 * Valores em dinheiro ficam ocultos até o membro confirmar a senha (olhinho).
 * Enquanto ocultos, os valores nem são enviados ao navegador.
 */
class Finance
{
    public const SESSION_KEY = 'finance_visible_until';

    public const DEFAULT_MINUTES = 15;

    /** Minutos que os valores ficam visíveis (Configurações). */
    public static function minutes(): int
    {
        return (int) (Setting::get('finance_minutes') ?: self::DEFAULT_MINUTES);
    }

    public static function visible(): bool
    {
        $until = session(self::SESSION_KEY);

        return $until && now()->timestamp < $until;
    }

    public static function unlock(): void
    {
        session([self::SESSION_KEY => now()->addMinutes(static::minutes())->timestamp]);
    }

    public static function lock(): void
    {
        session()->forget(self::SESSION_KEY);
    }

    /** "R$ 1.234,56" ou "R$ ••••" quando oculto. */
    public static function show(?int $cents): string
    {
        if ($cents === null) {
            return '—';
        }

        return static::visible() ? Money::format($cents) : 'R$ ••••';
    }
}
