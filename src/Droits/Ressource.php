<?php

namespace App\Droits;

/**
 * Types de ressources protégées (lignes de la matrice).
 */
enum Ressource: string
{
    case Objet = 'objet';
    case ObjetPersonnel = 'objet_personnel';
    case Piece = 'piece';
    case Look = 'look';
    case ItemLook = 'item_look';
    case Pret = 'pret';
    case Vente = 'vente';
    case Categorie = 'categorie';
    case Rangement = 'rangement';
    case RangementPartage = 'rangement_partage';
}
