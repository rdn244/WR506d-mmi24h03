<?php

namespace App\Droits;

/**
 * Lien entre la personne qui consulte et le propriétaire de la ressource (colonnes de la matrice).
 */
enum Relation: string
{
    case Proprietaire = 'proprietaire';
    case SousCompteMajeur = 'sous_compte_majeur';
    case SousCompteEnfant = 'sous_compte_enfant';
    case CompteLie = 'compte_lie';
    case Ami = 'ami';
    case Abonne = 'abonne';
    case Inconnu = 'inconnu';
    // Hors matrice : un utilisateur bloqué n'a accès à rien
    case Bloque = 'bloque';

    public function libelle(): string
    {
        return match ($this) {
            self::Proprietaire => 'Propriétaire',
            self::SousCompteMajeur => 'Sous-compte +18',
            self::SousCompteEnfant => 'Sous-compte enfant',
            self::CompteLie => 'Compte lié',
            self::Ami => 'Ami',
            self::Abonne => 'Abonné',
            self::Inconnu => 'Membre',
            self::Bloque => 'Bloqué',
        };
    }
}
