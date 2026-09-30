<?php

namespace App\Droits;

use App\Entity\SousCompte;
use App\Entity\User;

/**
 * Personne qui agit : l'utilisateur connecté, éventuellement sous l'un de ses sous-comptes.
 */
final readonly class Acteur
{
    public function __construct(
        public User $utilisateur,
        public ?SousCompte $sousCompte = null,
    ) {
    }

    public function estPrincipal(): bool
    {
        return null === $this->sousCompte;
    }

    public function getPrenom(): string
    {
        return (string) ($this->sousCompte?->getPrenom() ?? $this->utilisateur->getPrenom());
    }

    public function getInitiale(): string
    {
        return $this->sousCompte?->getInitiale() ?? $this->utilisateur->getInitiale();
    }

    public function getLibelle(): string
    {
        return $this->sousCompte?->getLibelleType() ?? 'Compte principal';
    }
}
