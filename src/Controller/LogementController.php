<?php

namespace App\Controller;

use App\Entity\Logement;
use App\Entity\Piece;
use App\Entity\User;
use App\Enum\TypePiece;
use App\Form\LogementType;
use App\Form\PiecesType;
use App\Security\Voter\ProprietaireVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LogementController extends AbstractController
{
    /**
     * Pièces cochées par défaut lors de la création d'un logement.
     */
    private const PIECES_PAR_DEFAUT = [
        TypePiece::Chambre,
        TypePiece::Salon,
        TypePiece::Cuisine,
        TypePiece::Grenier,
        TypePiece::Dressing,
    ];

    #[Route('/logement', name: 'app_logement', methods: ['GET'])]
    public function monLogement(#[CurrentUser] User $user): Response
    {
        $logement = $user->getLogements()->first();

        if (!$logement) {
            return $this->redirectToRoute('app_logement_nouveau');
        }

        return $this->redirectToRoute('app_logement_show', ['id' => $logement->getId()]);
    }

    #[Route('/logements/nouveau', name: 'app_logement_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(#[CurrentUser] User $user, Request $request, EntityManagerInterface $em): Response
    {
        $logement = new Logement();
        $form = $this->createForm(LogementType::class, $logement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->addLogement($logement);
            $em->persist($logement);
            $em->flush();

            return $this->redirectToRoute('app_logement_pieces', ['id' => $logement->getId()]);
        }

        return $this->render('logement/nouveau.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/logements/{id}', name: 'app_logement_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(ProprietaireVoter::GERER, 'logement')]
    public function show(Logement $logement): Response
    {
        return $this->render('logement/show.html.twig', [
            'logement' => $logement,
        ]);
    }

    #[Route('/logements/{id}/pieces', name: 'app_logement_pieces', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(ProprietaireVoter::GERER, 'logement')]
    public function pieces(Logement $logement, Request $request, EntityManagerInterface $em): Response
    {
        $premiereFois = $logement->getPieces()->isEmpty();
        $form = $this->createForm(PiecesType::class, [
            'types' => $premiereFois ? self::PIECES_PAR_DEFAUT : [],
            'personnalisees' => [],
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Respecte l'ordre de la grille plutôt que l'ordre de sélection
            $types = $form->get('types')->getData();
            foreach (TypePiece::predefinies() as $type) {
                if (\in_array($type, $types, true)) {
                    $logement->addPiece(Piece::depuisType($type));
                }
            }
            foreach ($form->get('personnalisees')->getData() as $nom) {
                if (null !== $nom && '' !== trim($nom)) {
                    $logement->addPiece((new Piece())->setNom($nom));
                }
            }
            $em->flush();

            return $this->redirectToRoute('app_logement_show', ['id' => $logement->getId()]);
        }

        return $this->render('logement/pieces.html.twig', [
            'logement' => $logement,
            'form' => $form,
            'premiere_fois' => $premiereFois,
        ]);
    }
}
