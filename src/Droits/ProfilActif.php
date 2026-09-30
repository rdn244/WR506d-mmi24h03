<?php

namespace App\Droits;

use App\Entity\SousCompte;
use App\Entity\User;
use App\Repository\SousCompteRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Profil sous lequel l'utilisateur navigue (compte principal ou sous-compte), mémorisé en session.
 */
final class ProfilActif
{
    private const CLE_SESSION = 'profil_actif';

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        private readonly SousCompteRepository $sousCompteRepository,
    ) {
    }

    public function acteur(): ?Acteur
    {
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return null;
        }

        return $this->pour($user);
    }

    public function pour(User $user): Acteur
    {
        $session = $this->requestStack->getCurrentRequest()?->hasSession() ? $this->requestStack->getSession() : null;
        $id = $session?->get(self::CLE_SESSION);

        if (null !== $id) {
            $sousCompte = $this->sousCompteRepository->find($id);
            if ($sousCompte?->getComptePrincipal() === $user) {
                return new Acteur($user, $sousCompte);
            }
            // Sous-compte supprimé ou appartenant à un autre compte
            $session->remove(self::CLE_SESSION);
        }

        return new Acteur($user);
    }

    public function activer(?SousCompte $sousCompte): void
    {
        $session = $this->requestStack->getSession();

        null === $sousCompte
            ? $session->remove(self::CLE_SESSION)
            : $session->set(self::CLE_SESSION, $sousCompte->getId());
    }
}
