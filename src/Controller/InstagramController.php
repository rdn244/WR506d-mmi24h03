<?php

namespace App\Controller;

use App\Entity\CompteInstagram;
use App\Entity\User;
use App\Instagram\ClientInstagram;
use App\Instagram\InstagramException;
use App\Repository\CompteInstagramRepository;
use App\Security\Voter\ComptePrincipalVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Connexion d'un compte Instagram au compte Dressing (OAuth « Instagram API with Instagram Login »).
 */
#[Route('/instagram')]
#[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
class InstagramController extends AbstractController
{
    private const CLE_ETAT = 'instagram_etat';

    public function __construct(
        private readonly ClientInstagram $instagram,
        #[Autowire('%env(default::INSTAGRAM_REDIRECT_URI)%')]
        private readonly ?string $urlRetourForcee,
    ) {
    }

    #[Route('/connecter', name: 'app_instagram_connecter', methods: ['POST'])]
    #[IsCsrfTokenValid('instagram')]
    public function connecter(Request $request): Response
    {
        if (!$this->instagram->estConfigure()) {
            $this->addFlash('erreur', 'La connexion Instagram n\'est pas encore disponible.');

            return $this->redirectToRoute('app_profil_parametres');
        }

        // Jeton anti-CSRF du parcours OAuth, vérifié au retour d'Instagram
        $etat = bin2hex(random_bytes(16));
        $request->getSession()->set(self::CLE_ETAT, $etat);

        return $this->redirect($this->instagram->urlAutorisation($this->urlRetour(), $etat));
    }

    #[Route('/retour', name: 'app_instagram_retour', methods: ['GET'])]
    public function retour(
        #[CurrentUser] User $user,
        Request $request,
        CompteInstagramRepository $comptes,
        EntityManagerInterface $em,
    ): Response {
        $etatAttendu = $request->getSession()->remove(self::CLE_ETAT);

        if ($request->query->has('error')) {
            $this->addFlash('erreur', 'Connexion à Instagram annulée.');

            return $this->redirectToRoute('app_profil_parametres');
        }

        $etat = (string) $request->query->get('state', '');
        $code = (string) $request->query->get('code', '');
        if (null === $etatAttendu || !hash_equals($etatAttendu, $etat) || '' === $code) {
            $this->addFlash('erreur', 'La connexion à Instagram a expiré, recommence.');

            return $this->redirectToRoute('app_profil_parametres');
        }

        try {
            $profil = $this->instagram->connecter($code, $this->urlRetour());
        } catch (InstagramException $e) {
            $this->addFlash('erreur', $e->getMessage());

            return $this->redirectToRoute('app_profil_parametres');
        }

        $dejaRelie = $comptes->findOneBy(['instagramId' => $profil->instagramId]);
        if (null !== $dejaRelie && $dejaRelie->getUtilisateur() !== $user) {
            $this->addFlash('erreur', sprintf('@%s est déjà connecté à un autre compte Dressing.', $profil->nomUtilisateur));

            return $this->redirectToRoute('app_profil_parametres');
        }

        $compte = $comptes->deUtilisateur($user);
        if (null !== $compte && $compte->getInstagramId() !== $profil->instagramId) {
            // Changement de compte Instagram : on remplace l'ancien
            $em->remove($compte);
            $em->flush();
            $compte = null;
        }

        if (null === $compte) {
            $em->persist(new CompteInstagram($user, $profil->instagramId, $profil->nomUtilisateur, $profil->jeton, $profil->expireLe));
        } else {
            $compte->mettreAJour($profil->nomUtilisateur, $profil->jeton, $profil->expireLe);
        }
        $em->flush();

        $this->addFlash('succes', sprintf('Ton compte Instagram @%s est connecté.', $profil->nomUtilisateur));

        return $this->redirectToRoute('app_profil_parametres');
    }

    #[Route('/deconnecter', name: 'app_instagram_deconnecter', methods: ['POST'])]
    #[IsCsrfTokenValid('instagram')]
    public function deconnecter(#[CurrentUser] User $user, CompteInstagramRepository $comptes, EntityManagerInterface $em): Response
    {
        $compte = $comptes->deUtilisateur($user);
        if (null !== $compte) {
            $em->remove($compte);
            $em->flush();
            $this->addFlash('succes', 'Ton compte Instagram est déconnecté.');
        }

        return $this->redirectToRoute('app_profil_parametres');
    }

    /**
     * Doit correspondre exactement à une URI déclarée dans l'application Meta (HTTPS).
     * INSTAGRAM_REDIRECT_URI permet de la forcer (tunnel HTTPS en développement).
     */
    private function urlRetour(): string
    {
        return '' !== (string) $this->urlRetourForcee
            ? (string) $this->urlRetourForcee
            : $this->generateUrl('app_instagram_retour', [], UrlGeneratorInterface::ABSOLUTE_URL);
    }
}
