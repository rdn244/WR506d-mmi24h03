<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\User;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use App\Repository\ObjetRepository;
use App\Security\Voter\DroitsVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Catégories d'objets : prédéfinies (lecture seule) et personnelles.
 */
#[Route('/categories')]
class CategorieController extends AbstractController
{
    #[Route('', name: 'app_categories', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, CategorieRepository $categories, ObjetRepository $objets): Response
    {
        return $this->render('categorie/index.html.twig', [
            'predefinies' => array_filter($categories->disponiblesPour($user), static fn (Categorie $c) => $c->estPredefinie()),
            'perso' => $categories->persoDe($user),
            'comptes' => $objets->comptesParCategorie($user),
        ]);
    }

    #[Route('/nouvelle', name: 'app_categorie_nouvelle', methods: ['GET', 'POST'])]
    public function nouvelle(#[CurrentUser] User $user, Request $request, CategorieRepository $categories, EntityManagerInterface $em): Response
    {
        $categorie = new Categorie($user);
        $this->denyAccessUnlessGranted(DroitsVoter::MODIFIER, $categorie);

        return $this->enregistrer($categorie, $user, $request, $categories, $em, 'Catégorie « %s » créée.');
    }

    #[Route('/{id}/modifier', name: 'app_categorie_modifier', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(DroitsVoter::MODIFIER, 'categorie')]
    public function modifier(Categorie $categorie, #[CurrentUser] User $user, Request $request, CategorieRepository $categories, EntityManagerInterface $em): Response
    {
        return $this->enregistrer($categorie, $user, $request, $categories, $em, 'Catégorie « %s » modifiée.');
    }

    #[Route('/{id}/supprimer', name: 'app_categorie_supprimer', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(DroitsVoter::MODIFIER, 'categorie')]
    #[IsCsrfTokenValid('categorie')]
    public function supprimer(Categorie $categorie, EntityManagerInterface $em): Response
    {
        // Les objets de la catégorie restent, sans catégorie (ON DELETE SET NULL)
        $em->remove($categorie);
        $em->flush();
        $this->addFlash('succes', sprintf('Catégorie « %s » supprimée.', $categorie->getNom()));

        return $this->redirectToRoute('app_categories');
    }

    private function enregistrer(
        Categorie $categorie,
        User $user,
        Request $request,
        CategorieRepository $categories,
        EntityManagerInterface $em,
        string $message,
    ): Response {
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid() && $categories->nomPris($user, (string) $categorie->getNom(), $categorie)) {
            $form->get('nom')->addError(new FormError(sprintf('La catégorie « %s » existe déjà.', $categorie->getNom())));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($categorie);
            $em->flush();
            $this->addFlash('succes', sprintf($message, $categorie->getNom()));

            return $this->redirectToRoute('app_categories');
        }

        return $this->render('categorie/formulaire.html.twig', [
            'form' => $form,
            'categorie' => $categorie,
        ]);
    }
}
