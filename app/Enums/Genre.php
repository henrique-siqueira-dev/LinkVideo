<?php

namespace App\Enums;

enum Genre: string
{
    case Acao = 'acao';
    case Aventura = 'aventura';
    case Animacao = 'animacao';
    case Comedia = 'comedia';
    case Documentario = 'documentario';
    case Drama = 'drama';
    case Fantasia = 'fantasia';
    case FiccaoCientifica = 'ficcao_cientifica';
    case Infantil = 'infantil';
    case Musical = 'musical';
    case Policial = 'policial';
    case Romance = 'romance';
    case Suspense = 'suspense';
    case Terror = 'terror';

    public function label(): string
    {
        return match ($this) {
            self::Acao => 'Ação',
            self::Aventura => 'Aventura',
            self::Animacao => 'Animação',
            self::Comedia => 'Comédia',
            self::Documentario => 'Documentário',
            self::Drama => 'Drama',
            self::Fantasia => 'Fantasia',
            self::FiccaoCientifica => 'Ficção científica',
            self::Infantil => 'Infantil',
            self::Musical => 'Musical',
            self::Policial => 'Policial',
            self::Romance => 'Romance',
            self::Suspense => 'Suspense',
            self::Terror => 'Terror',
        };
    }

    /** @return array<string, string> valor => rótulo, para selects */
    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $g) => [$g->value => $g->label()])->all();
    }
}
