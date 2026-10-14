<?php

namespace App\Droits;

use App\Entity\Categorie;
use App\Entity\Contenant;
use App\Entity\Logement;
use App\Entity\Look;
use App\Entity\Objet;
use App\Entity\Piece;
use App\Entity\User;
use App\Enum\NiveauSousCompte;

/**
 * Traduit une entité en ressource de la matrice et calcule le droit de l'acteur dessus.
 */
final class ControleAcces
{
    public function __construct(
        private readonly ResolveurRelation $resolveurRelation,
    ) {
    }

    public function droitSur(Acteur $acteur, object $sujet): Droit
    {
        return match (true) {
            $sujet instanceof Logement => $this->droitLogement($acteur, $sujet),
            $sujet instanceof Piece => $this->droitPiece($acteur, $sujet),
            $sujet instanceof Contenant => $this->droit(
                $acteur,
                $sujet->getPiece()->getLogement()->getProprietaire(),
                $sujet->isPartage() ? Ressource::RangementPartage : Ressource::Rangement,
            ),
            $sujet instanceof Look => $this->droit($acteur, $sujet->getProprietaire(), Ressource::Look),
            // Une catégorie prédéfinie se voit par tous et ne se modifie pas
            $sujet instanceof Categorie => null === $sujet->getProprietaire()
                ? Droit::Voir
                : $this->droit($acteur, $sujet->getProprietaire(), Ressource::Categorie),
            $sujet instanceof Objet => $this->droit(
                $acteur,
                $sujet->getProprietaire(),
                $sujet->isPersonnel() ? Ressource::ObjetPersonnel : Ressource::Objet,
            ),
            default => Droit::Rien,
        };
    }

    public function relation(Acteur $acteur, User $proprietaire): Relation
    {
        return $this->resolveurRelation->relation($acteur, $proprietaire);
    }

    private function droitPiece(Acteur $acteur, Piece $piece): Droit
    {
        return $this->droit($acteur, $piece->getLogement()->getProprietaire(), Ressource::Piece, $piece->isPartagee());
    }

    /**
     * Le logement suit les droits de ses pièces : on le voit si on voit au moins une pièce,
     * on le modifie (ajout de pièces) si on peut modifier les pièces.
     */
    private function droitLogement(Acteur $acteur, Logement $logement): Droit
    {
        $droit = $this->droit($acteur, $logement->getProprietaire(), Ressource::Piece);

        if (Droit::Rien === $droit) {
            foreach ($logement->getPieces() as $piece) {
                if ($this->droitPiece($acteur, $piece)->permet(Droit::Voir)) {
                    return Droit::Voir;
                }
            }
        }

        return $droit;
    }

    private function droit(Acteur $acteur, User $proprietaire, Ressource $ressource, bool $partage = false): Droit
    {
        $relation = $this->resolveurRelation->relation($acteur, $proprietaire);
        $droit = MatriceDroits::droit($ressource, $relation, $partage, $proprietaire->isComptePublic());

        // « Vue limitée » : lecture seule sur tout l'inventaire familial
        if (NiveauSousCompte::VueLimitee === $acteur->sousCompte?->getNiveau()) {
            $droit = $droit->plafonner(Droit::Voir);
        }

        return $droit;
    }
}
