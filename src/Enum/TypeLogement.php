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

    /**
     * Nom de l'icône Symfony UX Icons (jeu Phosphor).
     */
    public function icone(): string
    {
        return match ($this) {
            self::Maison => 'ph:house-duotone',
            self::Appartement => 'ph:building-apartment-duotone',
            self::Studio => 'ph:house-simple-duotone',
            self::Autre => 'ph:dots-three-circle-duotone',
        };
    }
}
