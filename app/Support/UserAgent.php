<?php

namespace App\Support;

class UserAgent
{
    /** Descrição curta do aparelho a partir do user agent (ex.: "Chrome · iPhone"). */
    public static function label(?string $agent): ?string
    {
        $agent = (string) $agent;

        if ($agent === '') {
            return null;
        }

        $browser = match (true) {
            str_contains($agent, 'WhatsApp') => 'WhatsApp',
            str_contains($agent, 'Instagram') => 'Instagram',
            str_contains($agent, 'FBAN') || str_contains($agent, 'FBAV') => 'Facebook',
            str_contains($agent, 'Edg/') => 'Edge',
            str_contains($agent, 'Chrome/') || str_contains($agent, 'CriOS') => 'Chrome',
            str_contains($agent, 'Firefox/') || str_contains($agent, 'FxiOS') => 'Firefox',
            str_contains($agent, 'Safari/') => 'Safari',
            default => 'Navegador',
        };

        $system = match (true) {
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'Mac OS X') => 'Mac',
            str_contains($agent, 'Linux') => 'Linux',
            default => null,
        };

        return $system ? "{$browser} · {$system}" : $browser;
    }
}
