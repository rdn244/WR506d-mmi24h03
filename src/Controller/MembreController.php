<?php

namespace App\Controller;

use App\Droits\GestionLiens;
use App\Entity\User;
use App\Enum\StatutLien;
use App\Enum\TypeLien;
use App\Form\InvitationType;
use App\Repository\ContenantRepository;
use App\Repository\LookRepository;
use App\Repository\LienRepository;
use App\Repository\UserRepository;
use App\Security\Voter\ComptePrincipalVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Requirement\Requirement;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsCsrfTokenValid;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Relations entre membres : amis (maquette 23), comptes liés, abonnements, blocages et page membre.
 * Réservé au compte principal : un sous-compte ne gère pas les relations.
 */
#[IsGranted(ComptePrincipalVoter::COMPTE_PRINCIPAL)]
class MembreController extends AbstractController
{
    public function __construct(
        private readonly LienRepository $liens,
        private readonly GestionLiens $gestionLiens,
    ) {
    }

    #[Route('/amis', name: 'app_amis', methods: ['GET'])]
    public function amis(#[CurrentUser] User $user, Request $request, UserRepository $users): Response
    {
        $recherche = trim((string) $request->query->get('q', ''));
        $resultats = $users->rechercher($recherche, $user);
        $etats = [];
        foreach ($resultats as $membre) {
            $etats[$membre->getId()] = $this->etat(TypeLien::Ami, $user, $membre);
        }

        return $this->render('membre/amis.html.twig', [
            'recherche' => $recherche,
            'resultats' => $resultats,
            'etats' => $etats,
            'demandes_recues' => $this->liens->recus(TypeLien::Ami, $user, StatutLien::EnAttente),
            'demandes_envoyees' => $this->liens->envoyes(TypeLien::Ami, $user, StatutLien::EnAttente),
            'amis' => $this->liens->acceptesDe(TypeLien::Ami, $user),
            'abonnements' => $this->liens->envoyes(TypeLien::Abonnement, $user, StatutLien::Accepte),
            'abonnes' => $this->liens->recus(TypeLien::Abonnement, $user, StatutLien::Accepte),
            'bloques' => $this->liens->envoyes(TypeLien::Blocage, $user, StatutLien::Accepte),
        ]);
    }

    #[Route('/comptes-lies', name: 'app_comptes_lies', methods: ['GET', 'POST'])]
    public function comptesLies(#[CurrentUser] User $user, Request $request, UserRepository $users): Response
    {
        $form = $this->createForm(InvitationType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $invite = $users->findOneBy(['email' => mb_strtolower(trim($form->get('email')->getData()))]);

            if (null === $invite) {
                $this->addFlash('erreur', 'Aucun compte Dressing n\'utilise cet email.');
            } else {
                try {
                    $lien = $this->gestionLiens->demander(TypeLien::CompteLie, $user, $invite);
                    $this->addFlash('succes', $lien->estAccepte()
                        ? sprintf('Vos comptes sont maintenant liés avec %s.', $invite->getPrenom())
                        : sprintf('Invitation envoyée à %s.', $invite->getPrenom()));
                } catch (\DomainException $e) {
                    $this->addFlash('erreur', $e->getMessage());
                }
            }

            return $this->redirectToRoute('app_comptes_lies');
        }

        return $this->render('membre/comptes_lies.html.twig', [
            'form' => $form,
            'invitations_recues' => $this->liens->recus(TypeLien::CompteLie, $user, StatutLien::EnAttente),
            'invitations_envoyees' => $this->liens->envoyes(TypeLien::CompteLie, $user, StatutLien::EnAttente),
            'comptes_lies' => $this->liens->acceptesDe(TypeLien::CompteLie, $user),
        ]);
    }

    #[Route('/membres/{id}', name: 'app_membre', requirements: ['id' => Requirement::DIGITS], methods: ['GET'])]
    public function membre(#[CurrentUser] User $user, User $membre, ContenantRepository $contenants, LookRepository $looks): Response
    {
        if ($membre === $user) {
            return $this->redirectToRoute('app_profil');
        }

        return $this->render('membre/show.html.twig', [
            'membre' => $membre,
            'rangements_partages' => $contenants->partagesDe($membre),
            'looks' => $looks->deProprietaire($membre),
            'etat_ami' => $this->etat(TypeLien::Ami, $user, $membre),
            'etat_lien' => $this->etat(TypeLien::CompteLie, $user, $membre),
            'abonne' => null !== $this->liens->trouver(TypeLien::Abonnement, $user, $membre),
            'bloque' => null !== $this->liens->trouver(TypeLien::Blocage, $user, $membre),
            'bloque_par' => null !== $this->liens->trouver(TypeLien::Blocage, $membre, $user),
        ]);
    }

    /**
     * Actions sur un membre, toutes en POST protégé par jeton CSRF.
     */
    #[Route('/membres/{id}/{action}', name: 'app_membre_action', requirements: [
        'id' => Requirement::DIGITS,
        'action' => 'ami|accepter-ami|retirer-ami|lier|accepter-lien|retirer-lien|abonner|desabonner|bloquer|debloquer',
    ], methods: ['POST'])]
    #[IsCsrfTokenValid('liens')]
    public function action(#[CurrentUser] User $user, User $membre, string $action, Request $request): Response
    {
        try {
            $message = match ($action) {
                'ami' => $this->gestionLiens->demander(TypeLien::Ami, $user, $membre)->estAccepte()
                    ? sprintf('Toi et %s êtes maintenant amis.', $membre->getPrenom())
                    : sprintf('Demande d\'ami envoyée à %s.', $membre->getPrenom()),
                'accepter-ami' => $this->executer(fn () => $this->gestionLiens->accepter(TypeLien::Ami, $user, $membre), sprintf('Toi et %s êtes maintenant amis.', $membre->getPrenom())),
                'retirer-ami' => $this->executer(fn () => $this->gestionLiens->retirer(TypeLien::Ami, $user, $membre)),
                'lier' => $this->gestionLiens->demander(TypeLien::CompteLie, $user, $membre)->estAccepte()
                    ? sprintf('Vos comptes sont maintenant liés avec %s.', $membre->getPrenom())
                    : sprintf('Invitation envoyée à %s.', $membre->getPrenom()),
                'accepter-lien' => $this->executer(fn () => $this->gestionLiens->accepter(TypeLien::CompteLie, $user, $membre), sprintf('Vos comptes sont maintenant liés avec %s.', $membre->getPrenom())),
                'retirer-lien' => $this->executer(fn () => $this->gestionLiens->retirer(TypeLien::CompteLie, $user, $membre)),
                'abonner' => $this->executer(fn () => $this->gestionLiens->sAbonner($user, $membre), sprintf('Tu suis maintenant %s.', $membre->getPrenom())),
                'desabonner' => $this->executer(fn () => $this->gestionLiens->seDesabonner($user, $membre)),
                'bloquer' => $this->executer(fn () => $this->gestionLiens->bloquer($user, $membre), sprintf('%s est bloqué.', $membre->getPrenom())),
                'debloquer' => $this->executer(fn () => $this->gestionLiens->debloquer($user, $membre)),
            };
            if (null !== $message) {
                $this->addFlash('succes', $message);
            }
        } catch (\DomainException $e) {
            $this->addFlash('erreur', $e->getMessage());
        }

        return $this->retour($request, $membre);
    }

    /**
     * État d'une amitié ou d'une liaison de comptes vu par l'utilisateur :
     * null (aucun lien), 'envoyee', 'recue', 'accepte' ou 'bloque'.
     */
    private function etat(TypeLien $type, User $user, User $membre): ?string
    {
        if (null !== $this->liens->entre(TypeLien::Blocage, $user, $membre)) {
            return 'bloque';
        }

        $lien = $this->liens->entre($type, $user, $membre);

        return match (true) {
            null === $lien => null,
            $lien->estAccepte() => 'accepte',
            $lien->getDemandeur() === $user => 'envoyee',
            default => 'recue',
        };
    }

    private function executer(callable $operation, ?string $message = null): ?string
    {
        $operation();

        return $message;
    }

    /**
     * Revient sur la page d'origine si elle est interne à l'application, sinon sur la page du membre.
     */
    private function retour(Request $request, User $membre): RedirectResponse
    {
        $retour = (string) $request->request->get('retour', '');

        if (str_starts_with($retour, '/') && !str_starts_with($retour, '//') && !str_contains($retour, '\\')) {
            return $this->redirect($retour);
        }

        return $this->redirectToRoute('app_membre', ['id' => $membre->getId()]);
    }
}
