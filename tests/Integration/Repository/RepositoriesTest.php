<?php

namespace App\Tests\Integration\Repository;

use App\DataFixtures\UtilisateurFixtures;
use App\Entity\Categorie;
use App\Entity\CompteInstagram;
use App\Entity\Lien;
use App\Entity\Logement;
use App\Entity\Piece;
use App\Entity\SousCompte;
use App\Enum\StatutLien;
use App\Enum\TypeLien;
use App\Repository\CategorieRepository;
use App\Repository\CompteInstagramRepository;
use App\Repository\ContenantRepository;
use App\Repository\LienRepository;
use App\Repository\LogementRepository;
use App\Repository\LookRepository;
use App\Repository\ObjetRepository;
use App\Repository\PieceRepository;
use App\Repository\SousCompteRepository;
use App\Repository\UserRepository;
use App\Tests\Integration\IntegrationTestCase;

/**
 * Requêtes des repositories sur les fixtures : ordre, filtres et limites que l'interface affiche.
 */
class RepositoriesTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerFixtures();
    }

    public function testLesCategoriesPredefiniesPuisPersonnelles(): void
    {
        $depot = $this->depot(CategorieRepository::class);
        $camille = $this->compte(UtilisateurFixtures::CAMILLE);

        $noms = array_map(static fn (Categorie $c) => $c->getNom(), $depot->disponiblesPour($camille));

        self::assertSame([...array_keys(Categorie::PREDEFINIES), 'Sport'], $noms, 'Prédéfinies dans leur ordre, puis celles de Camille');
        self::assertCount(\count(Categorie::PREDEFINIES), $depot->disponiblesPour($this->compte(UtilisateurFixtures::SOPHIE)), 'Sophie ne voit pas « Sport »');
        self::assertSame(['Sport'], array_map(static fn (Categorie $c) => $c->getNom(), $depot->persoDe($camille)));
    }

    public function testUnNomDeCategorieDejaPris(): void
    {
        $depot = $this->depot(CategorieRepository::class);
        $camille = $this->compte(UtilisateurFixtures::CAMILLE);
        $sport = $depot->persoDe($camille)[0];

        self::assertTrue($depot->nomPris($camille, 'HAUTS'), 'Une catégorie prédéfinie, sans tenir compte de la casse');
        self::assertTrue($depot->nomPris($camille, 'sport'));
        self::assertFalse($depot->nomPris($camille, 'Sport', $sport), 'Garder son propre nom en modifiant');
        self::assertFalse($depot->nomPris($this->compte(UtilisateurFixtures::SOPHIE), 'Sport'), 'Chacun ses catégories');
        self::assertNotNull($sport->getId());
    }

    public function testLesRangementsDUnMembre(): void
    {
        $depot = $this->depot(ContenantRepository::class);
        $camille = $this->compte(UtilisateurFixtures::CAMILLE);

        self::assertSame(['Armoire', 'Commode', 'Penderie', 'Carton du grenier'], array_map(static fn ($c) => $c->getNom(), $depot->deProprietaire($camille)));
        self::assertSame(['Commode'], array_map(static fn ($c) => $c->getNom(), $depot->partagesDe($camille)), 'Seuls les rangements partagés');
        self::assertSame([], $depot->deProprietaire($this->compte(UtilisateurFixtures::SOPHIE)));
    }

    public function testLInventaireEtSesComptesParCategorie(): void
    {
        $depot = $this->depot(ObjetRepository::class);
        $camille = $this->compte(UtilisateurFixtures::CAMILLE);
        $hauts = $this->em()->getRepository(Categorie::class)->findOneBy(['nom' => 'Hauts']);

        $objets = $depot->deProprietaire($camille);
        $comptes = $depot->comptesParCategorie($camille);

        self::assertCount(11, $objets);
        self::assertSame(2, $comptes[$hauts?->getId()]);
        self::assertSame(10, array_sum($comptes), 'L\'objet sans catégorie n\'est compté nulle part');
        self::assertSame([], $depot->deProprietaire($this->compte(UtilisateurFixtures::NINA)));
    }

    public function testLesLooksDuPlusRecentAuPlusAncien(): void
    {
        $looks = $this->depot(LookRepository::class)->deProprietaire($this->compte(UtilisateurFixtures::CAMILLE));

        self::assertCount(2, $looks);
        self::assertSame('Tenue d\'été', $looks[0]->getTitre(), 'Créé en dernier, affiché en premier');
    }

    public function testLesLiensAcceptesRecusEtEnvoyes(): void
    {
        $depot = $this->depot(LienRepository::class);
        $camille = $this->compte(UtilisateurFixtures::CAMILLE);
        $nina = $this->compte(UtilisateurFixtures::NINA);
        $this->em()->persist(new Lien(TypeLien::Ami, $nina, $camille));
        $this->em()->flush();

        $amis = $depot->acceptesDe(TypeLien::Ami, $camille);

        self::assertCount(1, $amis);
        self::assertSame(UtilisateurFixtures::SOPHIE, $amis[0]->autreQue($camille)->getEmail());
        self::assertCount(1, $depot->recus(TypeLien::Ami, $camille, StatutLien::EnAttente), 'La demande de Nina attend Camille');
        self::assertCount(1, $depot->envoyes(TypeLien::Ami, $nina, StatutLien::EnAttente));
        self::assertCount(0, $depot->envoyes(TypeLien::Ami, $camille, StatutLien::EnAttente));
    }

    public function testLaRechercheDeMembres(): void
    {
        $depot = $this->depot(UserRepository::class);
        $sophie = $this->compte(UtilisateurFixtures::SOPHIE);
        $prenoms = static fn (array $membres) => array_map(static fn ($m) => $m->getPrenom(), $membres);

        self::assertSame(['Camille'], $prenoms($depot->rechercher('cam', $sophie)));
        self::assertSame(['Alex', 'Camille'], $prenoms($depot->rechercher('Martin', $sophie)), 'Par nom, triés par prénom');
        self::assertSame(['Alex'], $prenoms($depot->rechercher('ALEX@dressing.test', $sophie)), 'Par email exact');
        self::assertSame([], $depot->rechercher('Sophie', $sophie), 'On ne se trouve pas soi-même');
        self::assertSame([], $depot->rechercher('a', $sophie), 'Au moins deux caractères');
        self::assertSame([], $depot->rechercher('%%', $sophie), 'Les jokers SQL sont échappés');
    }

    public function testLeCompteInstagramDUnMembre(): void
    {
        $depot = $this->depot(CompteInstagramRepository::class);
        $camille = $this->compte(UtilisateurFixtures::CAMILLE);
        $this->em()->persist(new CompteInstagram($camille, 'compte-1', 'camille.dressing'));
        $this->em()->flush();

        self::assertSame('camille.dressing', $depot->deUtilisateur($camille)?->getNomUtilisateur());
        self::assertNull($depot->deUtilisateur($this->compte(UtilisateurFixtures::SOPHIE)));
    }

    public function testLesDepotsSimples(): void
    {
        self::assertInstanceOf(Logement::class, $this->depot(LogementRepository::class)->findOneBy(['nom' => 'Appartement de Lyon']));
        self::assertCount(3, $this->depot(PieceRepository::class)->findAll());
        self::assertInstanceOf(SousCompte::class, $this->depot(SousCompteRepository::class)->findOneBy(['prenom' => 'Emma']));
        self::assertInstanceOf(Piece::class, $this->depot(PieceRepository::class)->findOneBy(['nom' => 'Grenier']));
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $classe
     *
     * @return T
     */
    private function depot(string $classe): object
    {
        return static::getContainer()->get($classe);
    }
}
