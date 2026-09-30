<?php

namespace App\Controller;

use App\Entity\Contenant;
use App\Entity\Piece;
use App\Form\ContenantType;
use App\Security\Voter\ProprietaireVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class PieceController extends AbstractController
{
    #[Route('/pieces/{id}', name: 'app_piece_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(ProprietaireVoter::GERER, 'piece')]
    public function show(Piece $piece, Request $request, EntityManagerInterface $em): Response
    {
        $contenant = new Contenant();
        $form = $this->createForm(ContenantType::class, $contenant);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $piece->addContenant($contenant);
            $em->persist($contenant);
            $em->flush();

            return $this->redirectToRoute('app_piece_show', ['id' => $piece->getId()]);
        }

        return $this->render('piece/show.html.twig', [
            'piece' => $piece,
            'form' => $form,
        ]);
    }
}
