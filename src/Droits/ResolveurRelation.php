<?php

namespace App\Droits;

use App\Entity\User;
use App\Enum\TypeLien;
use App\Repository\LienRepository;

/**
 * Détermine la colonne de la matrice des droits pour un acteur face au propriétaire d'une ressource.
 */
final class ResolveurRelation
{
    public function __construct(
        private readonly LienRepository $lienRepository,
    ) {
    }

    public function relation(Acteur $acteur, User $proprietaire): Relation
    {
        if (null !== $acteur->sousCompte) {
            // Un sous-compte n'a de droits que sur les ressources de son compte principal
            if ($acteur->sousCompte->getComptePrincipal() !== $proprietaire) {
                return Relation::Inconnu;
            }

            return $acteur->sousCompte->estMajeur() ? Relation::SousCompteMajeur : Relation::SousCompteEnfant;
        }

        $user = $acteur->utilisateur;

        return match (true) {
            $user === $proprietaire => Relation::Proprietaire,
            null !== $this->lienRepository->entre(TypeLien::Blocage, $user, $proprietaire) => Relation::Bloque,
            $this->lienRepository->existeAccepteEntre(TypeLien::CompteLie, $user, $proprietaire) => Relation::CompteLie,
            $this->lienRepository->existeAccepteEntre(TypeLien::Ami, $user, $proprietaire) => Relation::Ami,
            null !== $this->lienRepository->trouver(TypeLien::Abonnement, $user, $proprietaire) => Relation::Abonne,
            default => Relation::Inconnu,
        };
    }
}
