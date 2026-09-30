<?php

namespace App\Tests\Controller;

use App\Entity\Contenant;
use App\Entity\Lien;
use App\Entity\Logement;
use App\Entity\Piece;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Enum\NiveauSousCompte;
use App\Enum\TypeContenant;
use App\Enum\TypeLien;
use App\Enum\TypePiece;

/**
 * La matrice des droits appliquée aux écrans existants (logement, pièces, rangements).
 */
class DroitsTest extends AppWebTestCase
{
    private User $camille;
    private Logement $logement;
    private Piece $chambre;

    protected function setUp(): void
    {
        parent::setUp();

        // Camille (la propriétaire) : une chambre avec une armoire privée et une boîte partagée
        $this->camille = $this->creerUtilisateur('camille@example.com', 'Camille');
        $this->logement = $this->creerLogement($this->camille);
        $this->chambre = Piece::depuisType(TypePiece::Chambre);
        $this->logement->addPiece($this->chambre);
        $this->chambre->addContenant((new Contenant())->setNom('Armoire')->setType(TypeContenant::Armoire));
        $this->chambre->addContenant((new Contenant())->setNom('Boîte à souvenirs')->setType(TypeContenant::Boite)->setPartage(true));
        $this->em()->flush();
    }

    public function testUneAmieVoitLeLogementEnLectureSeuleEtLesRangementsPartages(): void
    {
        $this->client->loginUser($this->relier(TypeLien::Ami, 'sophie@example.com', 'Sophie'));

        $this->client->request('GET', $this->urlLogement());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.sous-titre', 'Logement de Camille');
        self::assertSelectorNotExists('a[href="'.$this->urlLogement().'/pieces"]');

        $crawler = $this->client->request('GET', $this->urlChambre());
        self::assertResponseIsSuccessful();
        self::assertSame(['Boîte à souvenirs'], $crawler->filter('.carte-ligne__titre')->each(static fn ($n) => $n->text()));
        self::assertSelectorNotExists('.ajout-contenant');
        self::assertSelectorNotExists('.partage');

        $this->client->request('POST', $this->urlChambre());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', $this->urlLogement().'/pieces');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnCompteLieNeVoitLaPieceQueSiElleEstPartagee(): void
    {
        $this->client->loginUser($this->relier(TypeLien::CompteLie, 'alex@example.com', 'Alex'));

        $this->client->request('GET', $this->urlChambre());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', $this->urlLogement());
        self::assertResponseStatusCodeSame(403);

        // Le client redémarre le noyau entre les requêtes : on recharge la pièce avant de la modifier
        $this->em()->find(Piece::class, $this->chambre->getId())->setPartagee(true);
        $this->em()->flush();

        // Pièce partagée : visible, et le compte lié modifie tous les rangements
        $crawler = $this->client->request('GET', $this->urlChambre());
        self::assertResponseIsSuccessful();
        self::assertCount(2, $crawler->filter('.carte-ligne__titre'));
        self::assertSelectorExists('.ajout-contenant');
        self::assertSelectorNotExists('.partage');

        $this->client->request('GET', $this->urlLogement());
        self::assertResponseIsSuccessful();
    }

    public function testUnAbonneNeVoitQueLesRangementsPartagesSurLaPageMembre(): void
    {
        $this->client->loginUser($this->relier(TypeLien::Abonnement, 'fan@example.com', 'Fan'));

        $this->client->request('GET', $this->urlChambre());
        self::assertResponseStatusCodeSame(403);

        $crawler = $this->client->request('GET', '/membres/'.$this->camille->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.entete-membre .etiquette', 'Abonné');
        self::assertSame(['Boîte à souvenirs'], $crawler->filter('.carte-ligne__titre')->each(static fn ($n) => $n->text()));
    }

    public function testUnInconnuNAccedeANien(): void
    {
        $this->client->loginUser($this->creerUtilisateur('inconnu@example.com', 'Inconnu'));

        $this->client->request('GET', '/membres/'.$this->camille->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.vide', 'ne partage rien');

        $this->client->request('GET', $this->urlLogement());
        self::assertResponseStatusCodeSame(403);
    }

    public function testBloquerRetireLAmitieEtTousLesAcces(): void
    {
        $sophie = $this->relier(TypeLien::Ami, 'sophie@example.com', 'Sophie');

        // Camille bloque Sophie depuis sa page membre
        $this->client->loginUser($this->camille);
        $crawler = $this->client->request('GET', '/membres/'.$sophie->getId());
        $this->client->submit($crawler->selectButton('Bloquer')->form());
        self::assertResponseRedirects();

        $this->client->loginUser($sophie);
        $this->client->request('GET', $this->urlLogement());
        self::assertResponseStatusCodeSame(403);
        $crawler = $this->client->request('GET', '/membres/'.$this->camille->getId());
        self::assertSelectorTextContains('.entete-membre .etiquette', 'Bloqué');
        self::assertCount(0, $crawler->filter('.actions-membre'));
    }

    public function testDemandeDAmiPuisAcceptation(): void
    {
        $sophie = $this->creerUtilisateur('sophie@example.com', 'Sophie');

        $this->client->loginUser($sophie);
        $crawler = $this->client->request('GET', '/amis?q=Cami');
        self::assertSelectorTextContains('.ligne-membre__nom', 'Camille');
        $this->client->submit($crawler->filter('.ligne-membre')->selectButton('Ajouter')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Demande d\'ami envoyée à Camille');

        // Tant que Camille n'a pas accepté, Sophie ne voit rien
        $this->client->request('GET', $this->urlLogement());
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->camille);
        $crawler = $this->client->request('GET', '/amis');
        self::assertSelectorTextContains('.section-membres', 'Demandes reçues (1)');
        $this->client->submit($crawler->selectButton('Accepter')->form());

        $this->client->loginUser($sophie);
        $this->client->request('GET', $this->urlLogement());
        self::assertResponseIsSuccessful();
    }

    public function testInvitationDeCompteLieParEmail(): void
    {
        $alex = $this->creerUtilisateur('alex@example.com', 'Alex');

        $this->client->loginUser($this->camille);
        $this->client->request('GET', '/comptes-lies');
        $this->client->submitForm('Inviter', ['invitation[email]' => 'ALEX@example.com']);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Invitation envoyée à Alex');

        $this->client->loginUser($alex);
        $crawler = $this->client->request('GET', '/comptes-lies');
        $this->client->submit($crawler->selectButton('Accepter')->form());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Vos comptes sont maintenant liés avec Camille');
    }

    public function testCreationDUnSousCompteEnfant(): void
    {
        $this->client->loginUser($this->camille);

        $this->client->request('GET', '/sous-comptes/nouveau');
        $this->client->submitForm('Créer le profil', [
            'sous_compte[prenom]' => 'Emma',
            'sous_compte[dateNaissance]' => (new \DateTimeImmutable('-8 years'))->format('Y-m-d'),
            'sous_compte[niveau]' => NiveauSousCompte::ControleParental->value,
        ]);
        self::assertResponseRedirects('/sous-comptes');

        $this->client->followRedirect();
        self::assertSelectorTextContains('.cartes-profil', 'Emma');
        self::assertSelectorTextContains('.cartes-profil', 'Contrôle parental');
    }

    public function testSousCompteEnfantAjouteDesRangementsMaisPasDePieces(): void
    {
        $this->activerSousCompte($this->creerSousCompte('Emma', '-8 years', NiveauSousCompte::Simplifie));

        $this->client->request('GET', $this->urlChambre());
        self::assertResponseIsSuccessful();
        $this->client->submitForm('Ajouter le rangement', [
            'contenant[nom]' => 'Coffre à jouets',
            'contenant[type]' => TypeContenant::Boite->value,
        ]);
        self::assertResponseRedirects($this->urlChambre());

        $this->client->request('GET', $this->urlLogement().'/pieces');
        self::assertResponseStatusCodeSame(403);

        // Pas de partage ni de gestion du compte depuis un sous-compte
        self::assertSelectorNotExists('.partage');
        foreach (['/profil/parametres', '/sous-comptes', '/amis', '/comptes-lies', '/logements/nouveau'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
    }

    public function testSousCompteEnVueLimiteeEstEnLectureSeule(): void
    {
        $this->activerSousCompte($this->creerSousCompte('Lucas', '-12 years', NiveauSousCompte::VueLimitee));

        $this->client->request('GET', $this->urlChambre());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.ajout-contenant');
    }

    public function testSousCompteMajeurModifieLesPieces(): void
    {
        $this->activerSousCompte($this->creerSousCompte('Jules', '-20 years'));

        $this->client->request('GET', $this->urlLogement().'/pieces');
        self::assertResponseIsSuccessful();
        $this->client->request('GET', $this->urlChambre());
        self::assertSelectorExists('.partage');
    }

    public function testRevenirAuComptePrincipalExigeLeMotDePasse(): void
    {
        $this->activerSousCompte($this->creerSousCompte('Emma', '-8 years', NiveauSousCompte::Simplifie));

        $this->client->request('GET', '/profils/principal');
        $this->client->submitForm('Continuer', ['compte_principal[motDePasse]' => 'mauvais']);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs', 'Mot de passe incorrect.');

        $this->client->submitForm('Continuer', ['compte_principal[motDePasse]' => self::MOT_DE_PASSE]);
        self::assertResponseRedirects('/accueil');

        $this->client->request('GET', '/profil/parametres');
        self::assertResponseIsSuccessful();
    }

    public function testUnSousCompteDUnAutreCompteNePeutPasEtreActive(): void
    {
        $emma = $this->creerSousCompte('Emma', '-8 years', NiveauSousCompte::Simplifie);
        $intrus = $this->creerUtilisateur('intrus@example.com', 'Intrus');
        $this->creerSousCompte('Petit', '-6 years', NiveauSousCompte::Simplifie, $intrus);

        // Jeton CSRF valide récupéré sur le sélecteur de profil de l'intrus
        $this->client->loginUser($intrus);
        $crawler = $this->client->request('GET', '/profil');
        $jeton = $crawler->filter('form[action^="/profils/"] input[name="_token"]')->attr('value');

        $this->client->request('POST', '/profils/'.$emma->getId().'/activer', ['_token' => $jeton]);
        self::assertResponseStatusCodeSame(404);
    }

    private function urlLogement(): string
    {
        return '/logements/'.$this->logement->getId();
    }

    private function urlChambre(): string
    {
        return '/pieces/'.$this->chambre->getId();
    }

    /**
     * Crée un membre relié à Camille (lien accepté).
     */
    private function relier(TypeLien $type, string $email, string $prenom): User
    {
        $membre = $this->creerUtilisateur($email, $prenom);
        $this->em()->persist((new Lien($type, $membre, $this->camille))->accepter());
        $this->em()->flush();

        return $membre;
    }

    private function creerSousCompte(string $prenom, string $age, ?NiveauSousCompte $niveau = null, ?User $principal = null): SousCompte
    {
        $sousCompte = (new SousCompte())
            ->setPrenom($prenom)
            ->setDateNaissance(new \DateTimeImmutable($age))
            ->setNiveau($niveau);
        ($principal ?? $this->camille)->addSousCompte($sousCompte);
        $this->em()->persist($sousCompte);
        $this->em()->flush();

        return $sousCompte;
    }

    /**
     * Connecte Camille puis bascule sur le sous-compte via le sélecteur de profil.
     */
    private function activerSousCompte(SousCompte $sousCompte): void
    {
        $this->client->loginUser($this->camille);
        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('form[action="/profils/'.$sousCompte->getId().'/activer"]')->form());
        self::assertResponseRedirects('/accueil');
    }
}
