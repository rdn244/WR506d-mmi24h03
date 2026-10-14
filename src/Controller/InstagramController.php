<?php

namespace App\Controller;

use App\Entity\CompteInstagram;
use App\Entity\User;
use App\Instagram\ClientZernio;
use App\Instagram\InstagramException;
use App\Repository\CompteInstagramRepository;
use App\Security\Voter\ComptePrincipalVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Connexion d'un compte Instagram au compte Dressing, via Zernio.
 */
#[Route('/instagram')]
#[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
class InstagramController extends AbstractController
{
    private const CLE_ETAT = 'instagram_etat';

    public function __construct(
        private readonly ClientZernio $zernio,
    ) {
    }

    #[Route('/connecter', name: 'app_instagram_connecter', methods: ['POST'])]
    #[IsCsrfTokenValid('instagram')]
    public function connecter(Request $request): Response
    {
        if (!$this->zernio->estConfigure()) {
            return $this->erreur('La connexion Instagram n\'est pas encore disponible.');
        }

        // Zernio conserve nos paramètres sur l'URL de retour : l'état y est vérifié contre la session
        $etat = bin2hex(random_bytes(16));
        $request->getSession()->set(self::CLE_ETAT, $etat);

        try {
            $url = $this->zernio->urlConnexion($this->generateUrl('app_instagram_retour', ['etat' => $etat], UrlGeneratorInterface::ABSOLUTE_URL));
        } catch (InstagramException $e) {
            return $this->erreur($e->getMessage());
        }

        return $this->redirect($url);
    }

    #[Route('/retour', name: 'app_instagram_retour', methods: ['GET'])]
    public function retour(
        #[CurrentUser] User $user,
        Request $request,
        CompteInstagramRepository $comptes,
        EntityManagerInterface $em,
    ): Response {
        $etatAttendu = $request->getSession()->remove(self::CLE_ETAT);
        if (!\is_string($etatAttendu) || !hash_equals($etatAttendu, (string) $request->query->get('etat', ''))) {
            return $this->erreur('La connexion à Instagram a expiré, recommence.');
        }

        if ($request->query->has('error')) {
            return $this->erreur($this->messageRefus($request->query));
        }

        $compteId = (string) $request->query->get('accountId', '');
        try {
            // Seul l'identifiant est repris du retour, et il doit appartenir à notre profil Zernio
            $compteZernio = '' === $compteId ? null : $this->zernio->compteInstagram($compteId);
        } catch (InstagramException $e) {
            return $this->erreur($e->getMessage());
        }

        if (null === $compteZernio) {
            return $this->erreur('Ce compte Instagram est introuvable, recommence.');
        }

        $dejaRelie = $comptes->findOneBy(['zernioId' => $compteZernio->id]);
        if (null !== $dejaRelie && $dejaRelie->getUtilisateur() !== $user) {
            return $this->erreur(sprintf('@%s est déjà connecté à un autre compte Dressing.', $compteZernio->nomUtilisateur));
        }

        $compte = $comptes->deUtilisateur($user);
        if (null !== $compte && $compte->getZernioId() !== $compteZernio->id) {
            // Changement de compte Instagram : l'ancien est retiré de Zernio puis remplacé
            $this->retirerDeZernio($compte);
            $em->remove($compte);
            $em->flush();
            $compte = null;
        }

        if (null === $compte) {
            $em->persist(new CompteInstagram($user, $compteZernio->id, $compteZernio->nomUtilisateur));
        } else {
            $compte->mettreAJour($compteZernio->nomUtilisateur);
        }
        $em->flush();

        $this->addFlash('succes', sprintf('Ton compte Instagram @%s est connecté.', $compteZernio->nomUtilisateur));

        return $this->redirectToRoute('app_profil_parametres');
    }

    #[Route('/deconnecter', name: 'app_instagram_deconnecter', methods: ['POST'])]
    #[IsCsrfTokenValid('instagram')]
    public function deconnecter(#[CurrentUser] User $user, CompteInstagramRepository $comptes, EntityManagerInterface $em): Response
    {
        $compte = $comptes->deUtilisateur($user);
        if (null === $compte) {
            return $this->redirectToRoute('app_profil_parametres');
        }

        if ($this->zernio->estConfigure()) {
            try {
                $this->zernio->deconnecter($compte->getZernioId());
            } catch (InstagramException $e) {
                // Le compte reste relié tant que Zernio le garde : sinon n'importe qui pourrait le reprendre
                return $this->erreur($e->getMessage());
            }
        }

        $em->remove($compte);
        $em->flush();
        $this->addFlash('succes', 'Ton compte Instagram est déconnecté.');

        return $this->redirectToRoute('app_profil_parametres');
    }

    /**
     * @param InputBag<string> $query
     */
    private function messageRefus(InputBag $query): string
    {
        if ('oauth_denied' === $query->get('error') && 'true' === $query->get('is_user_fixable')) {
            return 'Connexion à Instagram annulée.';
        }

        return sprintf('Instagram a refusé la connexion (%s).', $query->get('error_message') ?: $query->get('error'));
    }

    /**
     * Retrait au mieux : un échec ne doit pas bloquer le remplacement du compte.
     */
    private function retirerDeZernio(CompteInstagram $compte): void
    {
        try {
            $this->zernio->deconnecter($compte->getZernioId());
        } catch (InstagramException) {
            // Déjà journalisé par ClientZernio
        }
    }

    private function erreur(string $message): Response
    {
        $this->addFlash('erreur', $message);

        return $this->redirectToRoute('app_profil_parametres');
    }
}
