<?php

namespace App\Tests\Controller;

use App\Entity\CompteInstagram;
use App\Entity\User;
use App\Repository\CompteInstagramRepository;
use App\Tests\Instagram\ReponsesInstagram;

/**
 * Connexion d'un compte Instagram (appels à l'API simulés par ReponsesInstagram).
 */
class InstagramTest extends AppWebTestCase
{
    public function testConnexionPuisDeconnexion(): void
    {
        $user = $this->creerUtilisateur();
        $this->client->loginUser($user);

        $etat = $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['code' => 'code-valide', 'state' => $etat]);
        self::assertResponseRedirects('/profil/parametres');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Ton compte Instagram @'.ReponsesInstagram::PSEUDO.' est connecté.');
        self::assertSelectorTextContains('.instagram__pseudo', '@'.ReponsesInstagram::PSEUDO);

        $compte = $this->compteDe($user->getEmail());
        self::assertSame(ReponsesInstagram::ID, $compte->getInstagramId());
        self::assertSame('jeton-long', $compte->getJeton());
        self::assertFalse($compte->estExpire());
        // Le jeton n'apparaît jamais dans la page
        self::assertStringNotContainsString('jeton-long', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/profil');
        self::assertSelectorTextContains('.profil-entete__instagram', '@'.ReponsesInstagram::PSEUDO);

        $this->client->request('GET', '/profil/parametres');
        $this->client->submitForm('Déconnecter');
        self::assertResponseRedirects('/profil/parametres');
        self::assertNull($this->compteDe($user->getEmail()));
    }

    public function testLeRetourEstRefuseSiLEtatNeCorrespondPas(): void
    {
        $user = $this->creerUtilisateur();
        $this->client->loginUser($user);

        $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['code' => 'code-valide', 'state' => 'etat-forge']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'La connexion à Instagram a expiré');
        self::assertNull($this->compteDe($user->getEmail()));
    }

    public function testRefusDeLUtilisateurSurInstagram(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['error' => 'access_denied', 'error_reason' => 'user_denied']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'Connexion à Instagram annulée.');
    }

    public function testErreurDInstagramAffichee(): void
    {
        $user = $this->creerUtilisateur();
        $this->client->loginUser($user);

        $etat = $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['code' => 'code-refuse', 'state' => $etat]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'Invalid authorization code');
        self::assertNull($this->compteDe($user->getEmail()));
    }

    public function testUnCompteInstagramNeSeRelieQuAUnSeulCompteDressing(): void
    {
        $autre = $this->creerUtilisateur('autre@example.com', 'Autre');
        $this->em()->persist(new CompteInstagram($autre, ReponsesInstagram::ID, ReponsesInstagram::PSEUDO, 'jeton', null));
        $this->em()->flush();

        $user = $this->creerUtilisateur();
        $this->client->loginUser($user);
        $etat = $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['code' => 'code-valide', 'state' => $etat]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'est déjà connecté à un autre compte Dressing');
        self::assertNull($this->compteDe($user->getEmail()));
    }

    /**
     * Clique sur « Connecter Instagram » et renvoie l'état OAuth transmis à Instagram.
     */
    private function lancerConnexion(): string
    {
        $this->client->request('GET', '/profil/parametres');
        $this->client->submitForm('Connecter Instagram');

        $redirection = (string) $this->client->getResponse()->headers->get('Location');
        self::assertStringStartsWith('https://www.instagram.com/oauth/authorize?', $redirection);

        parse_str((string) parse_url($redirection, \PHP_URL_QUERY), $parametres);
        self::assertSame('id-test', $parametres['client_id']);
        self::assertSame('code', $parametres['response_type']);
        self::assertSame('instagram_business_basic', $parametres['scope']);
        self::assertSame('http://localhost/instagram/retour', $parametres['redirect_uri']);

        return $parametres['state'];
    }

    private function compteDe(string $email): ?CompteInstagram
    {
        $this->em()->clear();
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);

        return static::getContainer()->get(CompteInstagramRepository::class)->deUtilisateur($user);
    }
}
