<?php

namespace App\Controller;

use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class AccueilController extends AbstractController
{
    #[Route('/', name: 'app_onboarding', methods: ['GET'])]
    public function onboarding(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_accueil');
        }

        return $this->render('accueil/onboarding.html.twig');
    }

    #[Route('/accueil', name: 'app_accueil', methods: ['GET'])]
    public function accueil(#[CurrentUser] User $user): Response
    {
        return $this->render('accueil/accueil.html.twig', [
            'logement' => $user->getLogements()->first() ?: null,
        ]);
    }
}
