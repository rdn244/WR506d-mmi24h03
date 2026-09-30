<?php

namespace App\Enum;

enum TypeContenant: string
{
    case Armoire = 'armoire';
    case Commode = 'commode';
    case Etagere = 'etagere';
    case Boite = 'boite';
    case Autre = 'autre';

    public function libelle(): string
    {
        return match ($this) {
            self::Armoire => 'Armoire',
            self::Commode => 'Commode',
            self::Etagere => 'Étagère',
            self::Boite => 'Boîte / carton',
            self::Autre => 'Autre',
        };
    }

    /**
     * Nom de l'icône Symfony UX Icons (jeu Phosphor).
     */
    public function icone(): string
    {
        return match ($this) {
            self::Armoire => 'ph:lockers-duotone',
            self::Commode => 'ph:dresser-duotone',
            self::Etagere => 'ph:books-duotone',
            self::Boite => 'ph:package-duotone',
            self::Autre => 'ph:shapes-duotone',
        };
    }
}
