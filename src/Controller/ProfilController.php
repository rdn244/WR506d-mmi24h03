<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\MotDePasseType;
use App\Form\ProfilType;
use App\Security\Voter\ComptePrincipalVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/profil')]
class ProfilController extends AbstractController
{
    #[Route('', name: 'app_profil', methods: ['GET'])]
    public function profil(): Response
    {
        return $this->render('profil/profil.html.twig');
    }

    #[Route('/parametres', name: 'app_profil_parametres', methods: ['GET', 'POST'])]
    #[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
    public function parametres(
        #[CurrentUser] User $user,
        Request $request,
        FormFactoryInterface $formFactory,
        EntityManagerInterface $em,
        UserPasswordHasherInterface $hasher,
        Security $security,
    ): Response {
        $formProfil = $formFactory->createNamed('profil', ProfilType::class, $user);
        $formProfil->handleRequest($request);

        if ($formProfil->isSubmitted()) {
            if ($formProfil->isValid()) {
                $em->flush();
                // L'identifiant (email) a pu changer : on renouvelle le jeton pour ne pas être déconnecté
                $security->login($user, 'form_login', 'main');
                $this->addFlash('succes', 'Ton profil a été mis à jour.');

                return $this->redirectToRoute('app_profil_parametres');
            }

            // Annule les modifications invalides pour ne pas altérer l'utilisateur en session
            $em->refresh($user);
        }

        $formMotDePasse = $formFactory->createNamed('mot_de_passe', MotDePasseType::class);
        $formMotDePasse->handleRequest($request);

        if ($formMotDePasse->isSubmitted() && $formMotDePasse->isValid()) {
            $user->setPassword($hasher->hashPassword($user, $formMotDePasse->get('nouveau')->getData()));
            $em->flush();
            // Le hash a changé : on renouvelle le jeton pour ne pas être déconnecté
            $security->login($user, 'form_login', 'main');
            $this->addFlash('succes', 'Ton mot de passe a été modifié.');

            return $this->redirectToRoute('app_profil_parametres');
        }

        return $this->render('profil/parametres.html.twig', [
            'form_profil' => $formProfil,
            'form_mot_de_passe' => $formMotDePasse,
        ]);
    }
}
