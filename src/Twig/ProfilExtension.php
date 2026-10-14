<?php

namespace App\Twig;

use App\Droits\Acteur;
use App\Droits\ControleAcces;
use App\Droits\Droit;
use App\Droits\ProfilActif;
use App\Droits\Relation;
use App\Entity\Categorie;
use App\Entity\CompteInstagram;
use App\Entity\Objet;
use App\Entity\User;
use App\Instagram\ClientZernio;
use App\Repository\CompteInstagramRepository;
use Twig\Attribute\AsTwigFunction;

final class ProfilExtension
{
    public function __construct(
        private readonly ProfilActif $profilActif,
        private readonly ControleAcces $controleAcces,
        private readonly CompteInstagramRepository $comptesInstagram,
        private readonly ClientZernio $zernio,
    ) {
    }

    #[AsTwigFunction('profil_actif')]
    public function profilActif(): ?Acteur
    {
        return $this->profilActif->acteur();
    }

    #[AsTwigFunction('compte_instagram')]
    public function compteInstagram(User $user): ?CompteInstagram
    {
        return $this->comptesInstagram->deUtilisateur($user);
    }

    #[AsTwigFunction('instagram_disponible')]
    public function instagramDisponible(): bool
    {
        return $this->zernio->estConfigure();
    }

    /**
     * Le profil actif peut-il ajouter un objet à l'inventaire du compte (menu « + », scan) ?
     */
    #[AsTwigFunction('peut_ajouter_objet')]
    public function peutAjouterObjet(): bool
    {
        $acteur = $this->profilActif->acteur();

        return null !== $acteur
            && $this->controleAcces->droitSur($acteur, new Objet($acteur->utilisateur))->permet(Droit::Modifier);
    }

    /**
     * Le profil actif peut-il créer une catégorie personnelle pour le compte ?
     */
    #[AsTwigFunction('peut_creer_categorie')]
    public function peutCreerCategorie(): bool
    {
        $acteur = $this->profilActif->acteur();

        return null !== $acteur
            && $this->controleAcces->droitSur($acteur, new Categorie($acteur->utilisateur))->permet(Droit::Modifier);
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
