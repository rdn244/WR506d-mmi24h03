<?php

namespace App\Twig;

use App\Droits\Acteur;
use App\Droits\ControleAcces;
use App\Droits\ProfilActif;
use App\Droits\Relation;
use App\Entity\User;
use Twig\Attribute\AsTwigFunction;

final class ProfilExtension
{
    public function __construct(
        private readonly ProfilActif $profilActif,
        private readonly ControleAcces $controleAcces,
    ) {
    }

    #[AsTwigFunction('profil_actif')]
    public function profilActif(): ?Acteur
    {
        return $this->profilActif->acteur();
    }

    /**
     * Relation du profil actif envers un membre (libellé affiché sur sa page).
     */
    #[AsTwigFunction('relation_avec')]
    public function relationAvec(User $membre): ?Relation
    {
        $acteur = $this->profilActif->acteur();

        return null === $acteur ? null : $this->controleAcces->relation($acteur, $membre);
    }
}
