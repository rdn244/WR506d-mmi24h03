<?php

namespace App\Droits;

/**
 * Niveau d'accès d'une relation sur une ressource (légende de la matrice des droits).
 */
enum Droit: int
{
    case Rien = 0;
    case Voir = 1;
    case Modifier = 2;

    public function permet(self $requis): bool
    {
        return $this->value >= $requis->value;
    }

    public function plafonner(self $maximum): self
    {
        return $this->value > $maximum->value ? $maximum : $this;
    }
}
