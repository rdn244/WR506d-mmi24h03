<?php

namespace App\Controller;

use App\Entity\Look;
use App\Entity\PublicationInstagram;
use App\Entity\User;
use App\Enum\FormatPublication;
use App\Form\LookType;
use App\Form\PublicationInstagramType;
use App\Instagram\ClientZernio;
use App\Instagram\DemandePublication;
use App\Instagram\InstagramException;
use App\Photo\StockagePhotos;
use App\Repository\CompteInstagramRepository;
use App\Repository\LookRepository;
use App\Security\Voter\ComptePrincipalVoter;
use App\Security\Voter\DroitsVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Looks (tenues photographiées) et leur publication sur Instagram.
 */
#[Route('/looks')]
class LookController extends AbstractController
{
    public function __construct(
        private readonly StockagePhotos $photosLooks,
    ) {
    }

    /**
     * Looks du compte : un sous-compte voit ceux du compte principal, sans pouvoir les modifier.
     */
    #[Route('', name: 'app_looks', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, LookRepository $looks): Response
    {
        return $this->render('look/index.html.twig', [
            'looks' => $looks->deProprietaire($user),
        ]);
    }

    #[Route('/nouveau', name: 'app_look_nouveau', methods: ['GET', 'POST'])]
    #[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
    public function nouveau(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em): Response
    {
        $look = new Look($user);
        $form = $this->createForm(LookType::class, $look);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $look->setPhoto($this->photosLooks->enregistrer($form->get('photo')->getData()));
            $em->persist($look);
            $em->flush();

            $this->addFlash('succes', 'Ton look est enregistré.');

            return $this->redirectToRoute('app_look_show', ['id' => $look->getId()]);
        }

        return $this->render('look/nouveau.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_look_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(DroitsVoter::VOIR, 'look')]
    public function show(Look $look): Response
    {
        return $this->afficher($look, $this->peutPublier($look) ? $this->formulairePublication($look) : null);
    }

    #[Route('/{id}/photo', name: 'app_look_photo', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(DroitsVoter::VOIR, 'look')]
    public function photo(Look $look): BinaryFileResponse
    {
        return $this->photosLooks->reponse((string) $look->getPhoto());
    }

    #[Route('/{id}/instagram', name: 'app_look_instagram', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
    #[IsGranted(DroitsVoter::MODIFIER, 'look')]
    public function publier(
        Look $look,
        #[CurrentUser] User $user,
        Request $request,
        CompteInstagramRepository $comptes,
        ClientZernio $zernio,
        EntityManagerInterface $em,
    ): Response {
        $compte = $comptes->deUtilisateur($user);
        if (null === $compte || !$zernio->estConfigure()) {
            $this->addFlash('erreur', 'Connecte d\'abord ton compte Instagram.');

            return $this->redirectToRoute('app_profil_parametres', ['_fragment' => 'instagram']);
        }

        $form = $this->formulairePublication($look);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var DemandePublication $demande */
            $demande = $form->getData();
            $format = $demande->format ?? FormatPublication::Feed;
            $chemin = $this->photosLooks->chemin((string) $look->getPhoto());
            [$largeur, $hauteur] = @getimagesize($chemin) ?: [0, 0];

            if (!$format->accepteDimensions($largeur, $hauteur)) {
                $form->get('format')->addError(new FormError('Cette photo est trop étirée pour le feed (de 9:16 à 1,91:1). Publie-la en story.'));
            } else {
                try {
                    $resultat = $zernio->publier($compte->getZernioId(), $chemin, $format, (string) $demande->legende);
                    $em->persist(new PublicationInstagram($look, $format, $demande->legende, $resultat->zernioId, $resultat->url));
                    $em->flush();

                    $this->addFlash('succes', null === $resultat->url
                        ? 'Publication envoyée : Instagram la met en ligne dans quelques instants.'
                        : (FormatPublication::Story === $format ? 'Ta story est en ligne sur Instagram.' : 'Ton look est publié sur Instagram.'));

                    return $this->redirectToRoute('app_look_show', ['id' => $look->getId()]);
                } catch (InstagramException $e) {
                    $form->addError(new FormError($e->getMessage()));
                }
            }
        }

        return $this->afficher($look, $form);
    }

    #[Route('/{id}/supprimer', name: 'app_look_supprimer', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(DroitsVoter::MODIFIER, 'look')]
    #[IsCsrfTokenValid('look')]
    public function supprimer(Look $look, EntityManagerInterface $em): Response
    {
        $photo = (string) $look->getPhoto();
        $em->remove($look);
        $em->flush();
        $this->photosLooks->supprimer($photo);

        $this->addFlash('succes', 'Le look est supprimé.');

        return $this->redirectToRoute('app_looks');
    }

    /**
     * Seul le propriétaire, depuis son compte principal, publie ses looks (sur son propre Instagram).
     */
    private function peutPublier(Look $look): bool
    {
        return $this->isGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL) && $this->isGranted(DroitsVoter::MODIFIER, $look);
    }

    private function formulairePublication(Look $look): FormInterface
    {
        $demande = new DemandePublication();
        $demande->legende = $look->getLegendeParDefaut();

        return $this->createForm(PublicationInstagramType::class, $demande, [
            'action' => $this->generateUrl('app_look_instagram', ['id' => $look->getId()]),
        ]);
    }

    private function afficher(Look $look, ?FormInterface $formPublication): Response
    {
        return $this->render('look/show.html.twig', [
            'look' => $look,
            'form_publication' => $formPublication,
        ]);
    }
}
