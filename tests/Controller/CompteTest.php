<?php

namespace App\Tests\Controller;

use App\Entity\User;
use App\Repository\UserRepository;

class CompteTest extends AppWebTestCase
{
    public function testOnboardingAccessibleAnonymement(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.bouton--clair', 'Commencer');
    }

    public function testPagesProtegeesRedirigentVersConnexion(): void
    {
        foreach (['/accueil', '/profil', '/logement'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/connexion', null, $url);
        }
    }

    public function testInscriptionConnecteEtEnchaineSurLeLogement(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Continuer', [
            'inscription[prenom]' => 'Léa',
            'inscription[email]' => 'Lea@Example.com ',
            'inscription[plainPassword]' => self::MOT_DE_PASSE,
        ]);

        self::assertResponseRedirects('/logements/nouveau');

        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'lea@example.com']);
        self::assertInstanceOf(User::class, $user);
        self::assertNotSame(self::MOT_DE_PASSE, $user->getPassword());

        $this->client->request('GET', '/accueil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Bonjour Léa');
    }

    public function testInscriptionRefuseUnEmailDejaUtilise(): void
    {
        $this->creerUtilisateur();

        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Continuer', [
            'inscription[prenom]' => 'Autre',
            'inscription[email]' => 'lea@example.com',
            'inscription[plainPassword]' => self::MOT_DE_PASSE,
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs', 'Un compte existe déjà avec cet email.');
    }

    public function testInscriptionRefuseUnMotDePasseTropCourt(): void
    {
        $this->client->request('GET', '/inscription');
        $this->client->submitForm('Continuer', [
            'inscription[prenom]' => 'Léa',
            'inscription[email]' => 'lea@example.com',
            'inscription[plainPassword]' => 'court',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs', 'au moins 8 caractères');
    }

    public function testConnexionEtDeconnexion(): void
    {
        $this->creerUtilisateur();

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'lea@example.com',
            'password' => self::MOT_DE_PASSE,
        ]);
        self::assertResponseRedirects('/accueil');

        $crawler = $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.profil-entete__email', 'lea@example.com');

        $this->client->submit($crawler->filter('form[action="/deconnexion"]')->form());
        self::assertResponseRedirects('/connexion');

        $this->client->request('GET', '/profil');
        self::assertResponseRedirects('/connexion');
    }

    public function testConnexionAvecMauvaisMotDePasse(): void
    {
        $this->creerUtilisateur();

        $this->client->request('GET', '/connexion');
        $this->client->submitForm('Se connecter', [
            'email' => 'lea@example.com',
            'password' => 'mauvais-mot-de-passe',
        ]);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.erreurs', 'Identifiants invalides.');
        self::assertInputValueSame('email', 'lea@example.com');
    }

    public function testModificationDuProfilSansDeconnexion(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/profil/parametres');
        $this->client->submitForm('Enregistrer', [
            'profil[prenom]' => 'Léa',
            'profil[nom]' => 'Martin',
            'profil[email]' => 'lea.martin@example.com',
        ]);
        self::assertResponseRedirects('/profil/parametres');

        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.profil-entete__nom', 'Léa Martin');
        self::assertSelectorTextContains('.profil-entete__email', 'lea.martin@example.com');
    }

    public function testChangementDeMotDePasse(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/profil/parametres');
        $this->client->submitForm('Changer le mot de passe', [
            'mot_de_passe[actuel]' => 'faux',
            'mot_de_passe[nouveau][first]' => 'nouveaumdp123',
            'mot_de_passe[nouveau][second]' => 'nouveaumdp123',
        ]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs', 'Mot de passe actuel incorrect.');

        $this->client->submitForm('Changer le mot de passe', [
            'mot_de_passe[actuel]' => self::MOT_DE_PASSE,
            'mot_de_passe[nouveau][first]' => 'nouveaumdp123',
            'mot_de_passe[nouveau][second]' => 'nouveaumdp123',
        ]);
        self::assertResponseRedirects('/profil/parametres');

        $this->client->request('GET', '/profil');
        self::assertResponseIsSuccessful();
    }
}
