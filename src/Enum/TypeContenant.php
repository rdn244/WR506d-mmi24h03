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

    public function icone(): string
    {
        return match ($this) {
            self::Armoire => '🗄️',
            self::Commode => '🧰',
            self::Etagere => '📚',
            self::Boite => '📦',
            self::Autre => '🗃️',
        };
    }
}
