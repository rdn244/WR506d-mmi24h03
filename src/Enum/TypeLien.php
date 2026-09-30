<?php

namespace App\Enum;

enum TypeLien: string
{
    // Symétrique une fois acceptée
    case Ami = 'ami';
    // Co-gérant du foyer (conjoint, colocataire) : symétrique une fois accepté
    case CompteLie = 'compte_lie';
    // Le demandeur suit le destinataire, sans validation
    case Abonnement = 'abonnement';
    // Le demandeur bloque le destinataire
    case Blocage = 'blocage';
}
