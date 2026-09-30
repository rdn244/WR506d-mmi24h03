<?php

namespace App\Enum;

enum TypeLogement: string
{
    case Maison = 'maison';
    case Appartement = 'appartement';
    case Studio = 'studio';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Maison => 'Maison',
            self::Appartement => 'Appartement',
            self::Studio => 'Studio',
            self::Autre => 'Autre',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Maison => '🏠',
            self::Appartement => '🏢',
            self::Studio => '🏚️',
            self::Autre => '➕',
        };
    }
}
