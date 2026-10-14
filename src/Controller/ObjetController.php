<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\User;
use App\Form\ObjetType;
use App\Photo\PhotoBase64;
use App\Photo\StockagePhotos;
use App\Repository\CategorieRepository;
use App\Repository\ContenantRepository;
use App\Repository\ObjetRepository;
use App\Scan\AnalyseException;
use App\Scan\AnalyseurObjet;
use App\Security\Voter\DroitsVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Inventaire : liste filtrable, scan IA d'un objet, fiche, modification et suppression.
 */
#[Route('/objets')]
class ObjetController extends AbstractController
{
    /**
     * Valeur du filtre « sans catégorie ».
     */
    public const SANS_CATEGORIE = 'aucune';

    public function __construct(
        private readonly StockagePhotos $photosObjets,
        private readonly CategorieRepository $categories,
        private readonly ContenantRepository $contenants,
    ) {
    }

    /**
     * Objets du compte, filtrables par catégorie et par recherche (nom, marque, type, couleur).
     * Un sous-compte voit ceux du compte principal, hors objets personnels.
     */
    #[Route('', name: 'app_objets', methods: ['GET'])]
    public function index(#[CurrentUser] User $user, Request $request, ObjetRepository $objets): Response
    {
        $visibles = array_values(array_filter(
            $objets->deProprietaire($user),
            fn (Objet $objet) => $this->isGranted(DroitsVoter::VOIR, $objet),
        ));

        $comptes = [];
        foreach ($visibles as $objet) {
            $cle = $objet->getCategorie()?->getId() ?? self::SANS_CATEGORIE;
            $comptes[$cle] = ($comptes[$cle] ?? 0) + 1;
        }

        $filtre = $request->query->getString('categorie');
        $recherche = trim($request->query->getString('q'));
        $affiches = array_values(array_filter(
            $visibles,
            static fn (Objet $objet) => self::correspond($objet, $filtre, $recherche),
        ));

        return $this->render('objet/index.html.twig', [
            'objets' => $affiches,
            'total' => \count($visibles),
            // Seules les catégories qui contiennent des objets servent de filtre
            'categories' => array_values(array_filter(
                $this->categories->disponiblesPour($user),
                static fn (Categorie $categorie) => isset($comptes[$categorie->getId()]),
            )),
            'comptes' => $comptes,
            'filtre' => $filtre,
            'recherche' => $recherche,
        ]);
    }

    /**
     * Scan d'un objet (maquette 15) : la photo est analysée en direct par « analyser »,
     * puis la fiche vérifiée est enregistrée ici.
     */
    #[Route('/nouveau', name: 'app_objet_nouveau', methods: ['GET', 'POST'])]
    public function nouveau(
        #[CurrentUser] User $user,
        Request $request,
        AnalyseurObjet $analyseur,
        EntityManagerInterface $em,
    ): Response {
        $objet = new Objet($user);
        $this->denyAccessUnlessGranted(DroitsVoter::MODIFIER, $objet);

        // Arrivée depuis un filtre de l'inventaire : la catégorie est présélectionnée
        $categorie = $this->categories->find($request->query->getInt('categorie'));
        if (null !== $categorie && $this->isGranted(DroitsVoter::VOIR, $categorie)) {
            $objet->setCategorie($categorie);
        }

        $form = $this->createForm(ObjetType::class, $objet, $this->optionsFormulaire($user));
        $form->handleRequest($request);

        $photo = null;
        $dataUrl = $form->isSubmitted() ? (string) $form->get('photo')->getData() : '';
        if ('' !== $dataUrl) {
            try {
                $photo = PhotoBase64::depuisDataUrl($dataUrl);
            } catch (\InvalidArgumentException $e) {
                $form->addError(new FormError($e->getMessage()));
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Un objet personnel relève d'une autre ligne de la matrice : droit revérifié avec le choix final
            $this->denyAccessUnlessGranted(DroitsVoter::MODIFIER, $objet);

            if (null !== $photo) {
                $objet->setPhoto($this->photosObjets->enregistrerContenu($photo));
            }
            $em->persist($objet);
            $em->flush();

            $this->addFlash('succes', sprintf('« %s » est ajouté à ton dressing.', $objet->getNom()));

            return $this->redirectToRoute('app_objet_show', ['id' => $objet->getId()]);
        }

        return $this->render('objet/nouveau.html.twig', [
            'form' => $form,
            'analyse_disponible' => $analyseur->estConfigure(),
        ]);
    }

    /**
     * Analyse IA de la photo (appel en arrière-plan depuis la page de scan).
     */
    #[Route('/analyser', name: 'app_objet_analyser', methods: ['POST'])]
    #[IsCsrfTokenValid('scan')]
    public function analyser(
        #[CurrentUser] User $user,
        Request $request,
        AnalyseurObjet $analyseur,
        RateLimiterFactoryInterface $analysePhotoLimiter,
    ): JsonResponse {
        $this->denyAccessUnlessGranted(DroitsVoter::MODIFIER, new Objet($user));

        if (!$analyseur->estConfigure()) {
            return $this->json(['message' => 'La reconnaissance IA n\'est pas encore activée : remplis la fiche à la main.'], Response::HTTP_SERVICE_UNAVAILABLE);
        }

        // Les modèles gratuits ont un quota quotidien : on évite qu'un compte l'épuise
        if (!$analysePhotoLimiter->create('utilisateur-'.$user->getId())->consume()->isAccepted()) {
            return $this->json(['message' => 'Trop d\'analyses d\'affilée : patiente une minute.'], Response::HTTP_TOO_MANY_REQUESTS);
        }

        try {
            $photo = PhotoBase64::depuisDataUrl((string) $request->request->get('image', ''));
        } catch (\InvalidArgumentException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Le modèle choisit parmi les catégories du compte (prédéfinies et personnelles)
        $categories = [];
        foreach ($this->categories->disponiblesPour($user) as $categorie) {
            $categories[(int) $categorie->getId()] = (string) $categorie->getNom();
        }

        try {
            $resultat = $analyseur->analyser($photo, array_values($categories));
        } catch (AnalyseException $e) {
            return $this->json(['message' => $e->getMessage()], Response::HTTP_BAD_GATEWAY);
        }

        return $this->json(['champs' => $resultat->champs($categories), 'modele' => $resultat->modele]);
    }

    #[Route('/{id}', name: 'app_objet_show', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(DroitsVoter::VOIR, 'objet')]
    public function show(Objet $objet): Response
    {
        return $this->render('objet/show.html.twig', [
            'objet' => $objet,
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_objet_modifier', requirements: ['id' => Requirement::DIGITS], methods: ['GET', 'POST'])]
    #[IsGranted(DroitsVoter::MODIFIER, 'objet')]
    public function modifier(Objet $objet, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ObjetType::class, $objet, ['avec_photo' => false] + $this->optionsFormulaire($objet->getProprietaire()));
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(DroitsVoter::MODIFIER, $objet);
            $em->flush();
            $this->addFlash('succes', 'La fiche est mise à jour.');

            return $this->redirectToRoute('app_objet_show', ['id' => $objet->getId()]);
        }

        return $this->render('objet/modifier.html.twig', [
            'form' => $form,
            'objet' => $objet,
        ]);
    }

    #[Route('/{id}/photo', name: 'app_objet_photo', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    #[IsGranted(DroitsVoter::VOIR, 'objet')]
    public function photo(Objet $objet): BinaryFileResponse
    {
        return $this->photosObjets->reponse((string) $objet->getPhoto());
    }

    #[Route('/{id}/supprimer', name: 'app_objet_supprimer', requirements: ['id' => Requirement::DIGITS], methods: ['POST'])]
    #[IsGranted(DroitsVoter::MODIFIER, 'objet')]
    #[IsCsrfTokenValid('objet')]
    public function supprimer(Objet $objet, EntityManagerInterface $em): Response
    {
        $photo = $objet->getPhoto();
        $em->remove($objet);
        $em->flush();
        if (null !== $photo) {
            $this->photosObjets->supprimer($photo);
        }

        $this->addFlash('succes', 'L\'objet est supprimé.');

        return $this->redirectToRoute('app_objets');
    }

    /**
     * Catégories et rangements proposés : ceux du propriétaire uniquement.
     *
     * @return array{categories: list<Categorie>, contenants: list<\App\Entity\Contenant>}
     */
    private function optionsFormulaire(User $proprietaire): array
    {
        return [
            'categories' => $this->categories->disponiblesPour($proprietaire),
            'contenants' => $this->contenants->deProprietaire($proprietaire),
        ];
    }

    private static function correspond(Objet $objet, string $filtre, string $recherche): bool
    {
        $categorie = $objet->getCategorie()?->getId();
        if (self::SANS_CATEGORIE === $filtre && null !== $categorie) {
            return false;
        }
        if ('' !== $filtre && self::SANS_CATEGORIE !== $filtre && (string) $categorie !== $filtre) {
            return false;
        }
        if ('' === $recherche) {
            return true;
        }

        $texte = implode(' ', [$objet->getNom(), $objet->getMarque(), $objet->getType(), $objet->getCouleur(), $objet->getCategorie()?->getNom()]);

        return false !== mb_stripos($texte, $recherche);
    }
}
