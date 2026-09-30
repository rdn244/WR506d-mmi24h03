<?php

namespace App\Instagram;

/**
 * Compte Instagram autorisé et son jeton longue durée.
 */
final readonly class ProfilInstagram
{
    public function __construct(
        public string $instagramId,
        public string $nomUtilisateur,
        public string $jeton,
        public ?\DateTimeImmutable $expireLe,
    ) {
    }
}
