<?php

namespace App\Controller;

use App\Entity\Contenant;
use App\Entity\Piece;
use App\Form\ContenantType;
use App\Security\Voter\DroitsVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PieceController extends AbstractController
{
    #[Route('/pieces/{id}', name: 'app_piece_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(DroitsVoter::VOIR, 'piece')]
    public function show(Piece $piece, Request $request, EntityManagerInterface $em): Response
    {
        // Ajouter un rangement relève du droit « Rangement » de la matrice
        $contenant = (new Contenant())->setPiece($piece);
        $peutAjouter = $this->isGranted(DroitsVoter::MODIFIER, $contenant);

        $form = $this->createForm(ContenantType::class, $contenant, [
            'peut_partager' => $this->isGranted(DroitsVoter::MODIFIER, $piece),
        ]);

        if ($request->isMethod('POST')) {
            if (!$peutAjouter) {
                throw $this->createAccessDeniedException();
            }

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $piece->addContenant($contenant);
                $em->persist($contenant);
                $em->flush();

                return $this->redirectToRoute('app_piece_show', ['id' => $piece->getId()]);
            }
        }

        return $this->render('piece/show.html.twig', [
            'piece' => $piece,
            'form' => $peutAjouter ? $form : null,
        ]);
    }

    #[Route('/pieces/{id}/partage', name: 'app_piece_partage', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(DroitsVoter::MODIFIER, 'piece')]
    #[IsCsrfTokenValid('partage')]
    public function basculerPartage(Piece $piece, EntityManagerInterface $em): Response
    {
        $piece->setPartagee(!$piece->isPartagee());
        $em->flush();

        return $this->redirectToRoute('app_piece_show', ['id' => $piece->getId()]);
    }

    #[Route('/contenants/{id}/partage', name: 'app_contenant_partage', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsCsrfTokenValid('partage')]
    public function basculerPartageContenant(Contenant $contenant, EntityManagerInterface $em): Response
    {
        // Partager un rangement l'expose aux amis et abonnés : réservé à qui peut modifier la pièce
        $this->denyAccessUnlessGranted(DroitsVoter::MODIFIER, $contenant->getPiece());

        $contenant->setPartage(!$contenant->isPartage());
        $em->flush();

        return $this->redirectToRoute('app_piece_show', ['id' => $contenant->getPiece()->getId()]);
    }
}
