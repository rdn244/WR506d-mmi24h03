<?php

namespace App\Droits;

/**
 * Matrice des droits « Qui a le droit de voir ? » (séance 8), transcrite telle quelle.
 *
 * Colonnes : propriétaire, sous-compte +18, sous-compte enfant, compte lié, ami, abonné, inconnu.
 * Cas conditionnels :
 *  - pièce / compte lié : RIEN, sauf si la pièce est partagée (VOIR) ;
 *  - look et item de look / inconnu : VOIR si le compte du propriétaire est public, RIEN sinon ;
 *  - vente / sous-compte enfant : VOIR, l'affichage du prix pouvant être restreint.
 */
final class MatriceDroits
{
    private const M = Droit::Modifier;
    private const V = Droit::Voir;
    private const R = Droit::Rien;

    /**
     * Ordre des colonnes : voir COLONNES.
     */
    private const MATRICE = [
        'objet' => [self::M, self::M, self::V, self::M, self::V, self::R, self::R],
        'objet_personnel' => [self::M, self::R, self::R, self::R, self::R, self::R, self::R],
        'piece' => [self::M, self::M, self::V, self::R, self::V, self::R, self::R],
        'look' => [self::M, self::V, self::V, self::V, self::V, self::V, self::R],
        'item_look' => [self::M, self::V, self::V, self::V, self::V, self::V, self::R],
        'pret' => [self::M, self::V, self::V, self::M, self::V, self::R, self::R],
        'vente' => [self::M, self::V, self::V, self::V, self::V, self::V, self::V],
        'categorie' => [self::M, self::M, self::V, self::M, self::R, self::R, self::R],
        'rangement' => [self::M, self::M, self::M, self::M, self::R, self::R, self::R],
        'rangement_partage' => [self::M, self::M, self::V, self::M, self::V, self::V, self::R],
    ];

    private const COLONNES = [
        Relation::Proprietaire,
        Relation::SousCompteMajeur,
        Relation::SousCompteEnfant,
        Relation::CompteLie,
        Relation::Ami,
        Relation::Abonne,
        Relation::Inconnu,
    ];

    /**
     * @param bool $partage      la ressource est partagée (pièce partagée avec les comptes liés)
     * @param bool $comptePublic le compte du propriétaire est public
     */
    public static function droit(Ressource $ressource, Relation $relation, bool $partage = false, bool $comptePublic = false): Droit
    {
        if (Relation::Bloque === $relation) {
            return Droit::Rien;
        }

        if (Ressource::Piece === $ressource && Relation::CompteLie === $relation && $partage) {
            return Droit::Voir;
        }

        if (\in_array($ressource, [Ressource::Look, Ressource::ItemLook], true) && Relation::Inconnu === $relation) {
            return $comptePublic ? Droit::Voir : Droit::Rien;
        }

        return self::MATRICE[$ressource->value][array_search($relation, self::COLONNES, true)];
    }
}
