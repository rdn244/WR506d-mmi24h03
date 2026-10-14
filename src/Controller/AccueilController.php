<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\User;
use App\Repository\CategorieRepository;
use App\Repository\LookRepository;
use App\Repository\ObjetRepository;
use App\Security\Voter\DroitsVoter;
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

    /**
     * Nombre d'objets récents affichés sur l'accueil.
     */
    private const RECENTS = 4;

    #[Route('/accueil', name: 'app_accueil', methods: ['GET'])]
    public function accueil(#[CurrentUser] User $user, ObjetRepository $objets, CategorieRepository $categories, LookRepository $looks): Response
    {
        $nbPieces = 0;
        $nbContenants = 0;
        foreach ($user->getLogements() as $logement) {
            $nbPieces += $logement->getPieces()->count();
            foreach ($logement->getPieces() as $piece) {
                $nbContenants += $piece->getContenants()->count();
            }
        }

        // Ce que le profil actif a le droit de voir (un sous-compte ne voit pas les objets personnels)
        $visibles = array_values(array_filter($objets->deProprietaire($user), fn (Objet $o) => $this->isGranted(DroitsVoter::VOIR, $o)));
        $comptes = [];
        foreach ($visibles as $objet) {
            if (null !== $objet->getCategorie()) {
                $comptes[$objet->getCategorie()->getId()] = ($comptes[$objet->getCategorie()->getId()] ?? 0) + 1;
            }
        }

        return $this->render('accueil/accueil.html.twig', [
            'logement' => $user->getLogements()->first() ?: null,
            'statistiques' => [
                ['valeur' => \count($visibles), 'libelle' => 'Objets', 'route' => 'app_objets'],
                ['valeur' => $nbPieces, 'libelle' => 'Pièces', 'route' => 'app_logement'],
                ['valeur' => $nbContenants, 'libelle' => 'Rangements', 'route' => 'app_logement'],
                ['valeur' => \count($looks->deProprietaire($user)), 'libelle' => 'Looks', 'route' => 'app_looks'],
            ],
            // Catégories qui contiennent des objets d'abord, les plus fournies en tête
            'categories' => self::trierParCompte($categories->disponiblesPour($user), $comptes),
            'comptes' => $comptes,
            'recents' => \array_slice($visibles, 0, self::RECENTS),
        ]);
    }

    /**
     * @param list<Categorie>  $categories
     * @param array<int, int> $comptes
     *
     * @return list<Categorie>
     */
    private static function trierParCompte(array $categories, array $comptes): array
    {
        usort($categories, static fn (Categorie $a, Categorie $b) => ($comptes[$b->getId()] ?? 0) <=> ($comptes[$a->getId()] ?? 0));

        return $categories;
    }
}
