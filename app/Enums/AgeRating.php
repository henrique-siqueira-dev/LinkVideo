<?php

namespace App\Enums;

/**
 * Classificação indicativa brasileira (ClassInd).
 */
enum AgeRating: string
{
    case Livre = 'L';
    case Dez = '10';
    case Doze = '12';
    case Quatorze = '14';
    case Dezesseis = '16';
    case Dezoito = '18';

    public function label(): string
    {
        return $this === self::Livre ? 'Livre' : "{$this->value} anos";
    }

    public function description(): string
    {
        return $this === self::Livre
            ? 'Livre para todos os públicos'
            : "Não recomendado para menores de {$this->value} anos";
    }

    /** Classes do selo, seguindo as cores oficiais da ClassInd. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Livre => 'bg-[#0c9447] text-white',
            self::Dez => 'bg-[#0f7dc2] text-white',
            self::Doze => 'bg-[#f8c411] text-black',
            self::Quatorze => 'bg-[#e67824] text-white',
            self::Dezesseis => 'bg-[#db2827] text-white',
            self::Dezoito => 'bg-[#1d1815] text-white ring-1 ring-white/40',
        };
    }

    /** @return array<string, string> valor => rótulo, para selects */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $r) => [$r->value => $r->label()])->all();
    }
}
