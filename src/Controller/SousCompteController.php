<?php

namespace App\Controller;

use App\Droits\ProfilActif;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Form\ComptePrincipalType;
use App\Form\SousCompteType;
use App\Security\Voter\ComptePrincipalVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Sous-comptes (maquettes 27, 28) et sélecteur de profil (maquette 29).
 */
class SousCompteController extends AbstractController
{
    #[Route('/sous-comptes', name: 'app_sous_comptes', methods: ['GET'])]
    #[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
    public function liste(): Response
    {
        return $this->render('sous_compte/liste.html.twig');
    }

    #[Route('/sous-comptes/nouveau', name: 'app_sous_compte_nouveau', methods: ['GET', 'POST'])]
    #[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
    public function nouveau(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em): Response
    {
        $sousCompte = new SousCompte();
        $form = $this->createForm(SousCompteType::class, $sousCompte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->addSousCompte($sousCompte);
            $em->persist($sousCompte);
            $em->flush();
            $this->addFlash('succes', sprintf('Le profil de %s est créé.', $sousCompte->getPrenom()));

            return $this->redirectToRoute('app_sous_comptes');
        }

        return $this->render('sous_compte/formulaire.html.twig', [
            'form' => $form,
            'sous_compte' => null,
        ]);
    }

    #[Route('/sous-comptes/{id}', name: 'app_sous_compte_modifier', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
    public function modifier(#[CurrentUser] User $user, SousCompte $sousCompte, Request $request, EntityManagerInterface $em): Response
    {
        $this->verifierAppartenance($user, $sousCompte);

        $form = $this->createForm(SousCompteType::class, $sousCompte);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('succes', 'Le profil est mis à jour.');

            return $this->redirectToRoute('app_sous_comptes');
        }

        return $this->render('sous_compte/formulaire.html.twig', [
            'form' => $form,
            'sous_compte' => $sousCompte,
        ]);
    }

    #[Route('/sous-comptes/{id}/supprimer', name: 'app_sous_compte_supprimer', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
    #[IsCsrfTokenValid('profils')]
    public function supprimer(#[CurrentUser] User $user, SousCompte $sousCompte, EntityManagerInterface $em): Response
    {
        $this->verifierAppartenance($user, $sousCompte);

        $user->removeSousCompte($sousCompte);
        $em->flush();
        $this->addFlash('succes', 'Le profil est supprimé.');

        return $this->redirectToRoute('app_sous_comptes');
    }

    /**
     * Passer d'un profil à un sous-compte (ou d'un sous-compte à un autre) ne demande pas de mot de passe.
     */
    #[Route('/profils/{id}/activer', name: 'app_profil_activer', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsCsrfTokenValid('profils')]
    public function activer(#[CurrentUser] User $user, SousCompte $sousCompte, ProfilActif $profilActif): Response
    {
        $this->verifierAppartenance($user, $sousCompte);
        $profilActif->activer($sousCompte);

        return $this->redirectToRoute('app_accueil');
    }

    /**
     * Revenir au compte principal exige son mot de passe : un sous-compte ne doit pas pouvoir s'octroyer ses droits.
     */
    #[Route('/profils/principal', name: 'app_profil_principal', methods: ['GET', 'POST'])]
    public function principal(#[CurrentUser] User $user, Request $request, ProfilActif $profilActif): Response
    {
        if ($profilActif->pour($user)->estPrincipal()) {
            return $this->redirectToRoute('app_accueil');
        }

        $form = $this->createForm(ComptePrincipalType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $profilActif->activer(null);

            return $this->redirectToRoute('app_accueil');
        }

        return $this->render('sous_compte/principal.html.twig', [
            'form' => $form,
        ]);
    }

    private function verifierAppartenance(User $user, SousCompte $sousCompte): void
    {
        if ($sousCompte->getComptePrincipal() !== $user) {
            throw $this->createNotFoundException();
        }
    }
}
