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
        $nbPieces = 0;
        $nbContenants = 0;
        foreach ($user->getLogements() as $logement) {
            $nbPieces += $logement->getPieces()->count();
            foreach ($logement->getPieces() as $piece) {
                $nbContenants += $piece->getContenants()->count();
            }
        }

        return $this->render('accueil/accueil.html.twig', [
            'logement' => $user->getLogements()->first() ?: null,
            'statistiques' => [
                ['valeur' => $user->getLogements()->count(), 'libelle' => 'Logements'],
                ['valeur' => $nbPieces, 'libelle' => 'Pièces'],
                ['valeur' => $nbContenants, 'libelle' => 'Contenants'],
            ],
        ]);
    }
}
