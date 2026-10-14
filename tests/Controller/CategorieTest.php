<?php

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Lien;
use App\Entity\Objet;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Enum\NiveauSousCompte;
use App\Enum\TypeLien;

/**
 * Catégories d'objets : prédéfinies et personnelles, avec les droits de la matrice.
 */
class CategorieTest extends AppWebTestCase
{
    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();
        $this->camille = $this->creerUtilisateur('camille@example.com', 'Camille');
    }

    public function testLesCategoriesPredefiniesSontProposeesATous(): void
    {
        $this->client->loginUser($this->camille);

        $crawler = $this->client->request('GET', '/categories');

        self::assertResponseIsSuccessful();
        self::assertSame(array_keys(Categorie::PREDEFINIES), $crawler->filter('.tuiles--categories .tuile__nom')->each(static fn ($n) => $n->text()));
        // Une catégorie prédéfinie ne se modifie pas
        $this->client->request('GET', '/categories/'.$this->predefinie('Hauts')->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);
    }

    public function testCreerModifierPuisSupprimerUneCategoriePerso(): void
    {
        $this->client->loginUser($this->camille);

        $crawler = $this->client->request('GET', '/categories/nouvelle');
        $this->client->submit($crawler->selectButton('Créer la catégorie')->form([
            'categorie[nom]' => '  Tenues   de sport ',
            'categorie[icone]' => 'ph:basketball-duotone',
        ]));
        self::assertResponseRedirects('/categories');

        $sport = $this->em()->getRepository(Categorie::class)->findOneBy(['proprietaire' => $this->camille]);
        self::assertSame('Tenues de sport', $sport->getNom());
        self::assertSame('ph:basketball-duotone', $sport->getIcone());

        // Elle est proposée au scan, après les catégories prédéfinies
        $crawler = $this->client->request('GET', '/objets/nouveau');
        $libelles = $crawler->filter('.puces--categories .puce')->each(static fn ($n) => trim($n->text()));
        self::assertSame('Tenues de sport', end($libelles));

        // Le noyau a redémarré : on recharge les entités avant de les relier
        $sport = $this->em()->find(Categorie::class, $sport->getId());
        $objet = (new Objet($this->em()->find(User::class, $this->camille->getId())))->setNom('Short de running')->setCategorie($sport);
        $this->em()->persist($objet);
        $this->em()->flush();

        $crawler = $this->client->request('GET', '/categories/'.$sport->getId().'/modifier');
        $this->client->submit($crawler->selectButton('Enregistrer')->form(['categorie[nom]' => 'Sport']));
        self::assertResponseRedirects('/categories');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.liste-cartes .carte-ligne__titre', 'Sport');
        self::assertSelectorTextContains('.liste-cartes .carte-ligne__detail', '1 objet');

        $crawler = $this->client->request('GET', '/categories');
        $this->client->submit($crawler->filter('form[action="/categories/'.$sport->getId().'/supprimer"]')->form());
        self::assertResponseRedirects('/categories');

        // L'objet reste, sans catégorie
        $this->em()->clear();
        self::assertNull($this->em()->find(Objet::class, $objet->getId())->getCategorie());
        self::assertNull($this->em()->find(Categorie::class, $sport->getId()));
    }

    public function testUnNomDejaPrisEstRefuse(): void
    {
        $this->client->loginUser($this->camille);

        $crawler = $this->client->request('GET', '/categories/nouvelle');
        $this->client->submit($crawler->selectButton('Créer la catégorie')->form(['categorie[nom]' => 'hauts']));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs', 'La catégorie « hauts » existe déjà.');
        self::assertSame(0, $this->em()->getRepository(Categorie::class)->count(['proprietaire' => $this->camille]));
    }

    public function testUneAmieNePeutNiVoirNiModifierMesCategories(): void
    {
        $sport = (new Categorie($this->camille))->setNom('Sport');
        $this->em()->persist($sport);
        $amie = $this->creerUtilisateur('sophie@example.com', 'Sophie');
        $this->em()->persist((new Lien(TypeLien::Ami, $amie, $this->camille))->accepter());
        $this->em()->flush();

        $this->client->loginUser($amie);
        $crawler = $this->client->request('GET', '/categories');
        self::assertSame(0, $crawler->filter('.liste-cartes')->count());

        $this->client->request('GET', '/categories/'.$sport->getId().'/modifier');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnSousCompteEnfantNePeutPasCreerDeCategorie(): void
    {
        $emma = (new SousCompte())->setPrenom('Emma')->setDateNaissance(new \DateTimeImmutable('-9 years'))->setNiveau(NiveauSousCompte::Simplifie);
        $this->camille->addSousCompte($emma);
        $this->em()->persist($emma);
        $this->em()->flush();

        $this->client->loginUser($this->camille);
        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('form[action="/profils/'.$emma->getId().'/activer"]')->form());

        $this->client->request('GET', '/categories');
        self::assertSelectorNotExists('a[href="/categories/nouvelle"]');
        $this->client->request('GET', '/categories/nouvelle');
        self::assertResponseStatusCodeSame(403);
    }

    private function predefinie(string $nom): Categorie
    {
        return $this->em()->getRepository(Categorie::class)->findOneBy(['nom' => $nom, 'proprietaire' => null]);
    }
}
