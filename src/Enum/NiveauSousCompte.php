<?php

namespace App\Enum;

/**
 * Permissions d'un sous-compte enfant (maquette 28).
 */
enum NiveauSousCompte: string
{
    case Simplifie = 'simplifie';
    case ControleParental = 'controle_parental';
    case VueLimitee = 'vue_limitee';

    public function libelle(): string
    {
        return match ($this) {
            self::Simplifie => 'Mode simplifié',
            self::ControleParental => 'Contrôle parental',
            self::VueLimitee => 'Vue limitée',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Simplifie => 'Interface allégée avec navigation facile, idéale pour les plus jeunes.',
            self::ControleParental => 'Achats, ventes et publications nécessitent une validation du compte principal.',
            self::VueLimitee => 'Accès en lecture seule à l\'inventaire familial, sans actions de vente ou de prêt.',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Simplifie => 'ph:balloon-duotone',
            self::ControleParental => 'ph:shield-check-duotone',
            self::VueLimitee => 'ph:eye-duotone',
        };
    }
}
