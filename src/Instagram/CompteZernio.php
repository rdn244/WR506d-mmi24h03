<?php

namespace App\Instagram;

/**
 * Compte Instagram rattaché au profil Zernio.
 */
final readonly class CompteZernio
{
    public function __construct(
        public string $id,
        public string $nomUtilisateur,
    ) {
    }
}
