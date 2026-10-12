<?php

namespace App\Tests\Controller;

use App\Entity\CompteInstagram;
use App\Entity\User;
use App\Instagram\ClientZernio;
use App\Repository\CompteInstagramRepository;
use App\Tests\Instagram\ReponsesZernio;

/**
 * Connexion d'un compte Instagram via Zernio (appels simulés par ReponsesZernio).
 */
class InstagramTest extends AppWebTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        ReponsesZernio::$requetes = [];
    }

    public function testConnexionPuisDeconnexion(): void
    {
        $user = $this->creerUtilisateur();
        $this->client->loginUser($user);

        $etat = $this->lancerConnexion();
        // Le pseudo est relu chez Zernio : celui du retour (falsifiable) est ignoré
        $this->client->request('GET', '/instagram/retour', [
            'etat' => $etat,
            'connected' => 'instagram',
            'profileId' => 'profil-test',
            'accountId' => ReponsesZernio::COMPTE_ID,
            'username' => 'pseudo-falsifie',
        ]);
        self::assertResponseRedirects('/profil/parametres');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Ton compte Instagram @'.ReponsesZernio::PSEUDO.' est connecté.');
        self::assertSelectorTextContains('.instagram__pseudo', '@'.ReponsesZernio::PSEUDO);
        self::assertSame(ReponsesZernio::COMPTE_ID, $this->compteDe($user->getEmail())?->getZernioId());

        $this->client->request('GET', '/profil');
        self::assertSelectorTextContains('.profil-entete__instagram', '@'.ReponsesZernio::PSEUDO);

        $this->client->request('GET', '/profil/parametres');
        $this->client->submitForm('Déconnecter');
        self::assertResponseRedirects('/profil/parametres');
        self::assertNull($this->compteDe($user->getEmail()));
        // Le compte est aussi retiré du profil Zernio
        self::assertNotNull(ReponsesZernio::derniere('DELETE', ClientZernio::URL_API.'/accounts/'.ReponsesZernio::COMPTE_ID));
    }

    public function testLeRetourEstRefuseSiLEtatNeCorrespondPas(): void
    {
        $user = $this->creerUtilisateur();
        $this->client->loginUser($user);

        $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['etat' => 'etat-forge', 'accountId' => ReponsesZernio::COMPTE_ID]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'La connexion à Instagram a expiré');
        self::assertNull($this->compteDe($user->getEmail()));
    }

    public function testRefusDeLUtilisateurSurInstagram(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $etat = $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['etat' => $etat, 'error' => 'oauth_denied', 'platform' => 'instagram', 'is_user_fixable' => 'true']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'Connexion à Instagram annulée.');
    }

    public function testErreurDeZernioAffichee(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $etat = $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['etat' => $etat, 'error' => 'account_mismatch', 'error_message' => 'Wrong account']);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'Instagram a refusé la connexion (Wrong account).');
    }

    public function testUnCompteQuiNEstPasInstagramDansLeProfilEstRefuse(): void
    {
        $user = $this->creerUtilisateur();
        $this->client->loginUser($user);

        $etat = $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['etat' => $etat, 'accountId' => ReponsesZernio::COMPTE_TIKTOK]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'Ce compte Instagram est introuvable');
        self::assertNull($this->compteDe($user->getEmail()));
    }

    public function testUnCompteInstagramNeSeRelieQuAUnSeulCompteDressing(): void
    {
        $autre = $this->creerUtilisateur('autre@example.com', 'Autre');
        $this->em()->persist(new CompteInstagram($autre, ReponsesZernio::COMPTE_ID, ReponsesZernio::PSEUDO));
        $this->em()->flush();

        $user = $this->creerUtilisateur();
        $this->client->loginUser($user);
        $etat = $this->lancerConnexion();
        $this->client->request('GET', '/instagram/retour', ['etat' => $etat, 'accountId' => ReponsesZernio::COMPTE_ID]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.flash--erreur', 'est déjà connecté à un autre compte Dressing');
        self::assertNull($this->compteDe($user->getEmail()));
    }

    /**
     * Clique sur « Connecter Instagram » et renvoie l'état glissé dans l'URL de retour transmise à Zernio.
     */
    private function lancerConnexion(): string
    {
        $this->client->request('GET', '/profil/parametres');
        $this->client->submitForm('Connecter Instagram');
        self::assertResponseRedirects(ReponsesZernio::URL_AUTORISATION);

        $requete = ReponsesZernio::derniere('GET', ClientZernio::URL_API.'/connect/instagram');
        self::assertNotNull($requete);
        self::assertContains('Authorization: Bearer sk_test', $requete['options']['normalized_headers']['authorization']);

        parse_str((string) parse_url($requete['url'], \PHP_URL_QUERY), $parametres);
        self::assertSame('profil-test', $parametres['profileId']);
        self::assertStringStartsWith('http://localhost/instagram/retour?etat=', $parametres['redirect_url']);

        parse_str((string) parse_url($parametres['redirect_url'], \PHP_URL_QUERY), $retour);

        return $retour['etat'];
    }

    private function compteDe(string $email): ?CompteInstagram
    {
        $this->em()->clear();
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);

        return static::getContainer()->get(CompteInstagramRepository::class)->deUtilisateur($user);
    }
}
