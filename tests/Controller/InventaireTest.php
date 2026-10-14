<?php

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Objet;
use App\Entity\User;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Inventaire filtré par catégorie et par recherche, accueil et modification d'une fiche.
 */
class InventaireTest extends AppWebTestCase
{
    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();
        $this->camille = $this->creerUtilisateur('camille@example.com', 'Camille');

        $this->creerObjet('Pull marine', 'Hauts', 'Petit Bateau');
        $this->creerObjet('Chemise blanche', 'Hauts', 'Sandro');
        $this->creerObjet('Baskets', 'Chaussures', 'Veja');
        $this->creerObjet('Boîte à couture', null, null);
        $this->em()->flush();
        $this->client->loginUser($this->camille);
    }

    public function testLesPucesNeMontrentQueLesCategoriesUtilisees(): void
    {
        $crawler = $this->client->request('GET', '/objets');

        self::assertSelectorTextContains('.sous-titre', '4 objets');
        self::assertSame(
            ['Tous 4', 'Hauts 2', 'Chaussures 1', 'Sans catégorie 1'],
            $crawler->filter('.filtres .filtre:not(.filtre--reglage)')->each(static fn ($n) => preg_replace('/\s+/', ' ', trim($n->text()))),
        );
        self::assertCount(4, $crawler->filter('.galerie .vignette'));
    }

    public function testFiltrerParCategorie(): void
    {
        $crawler = $this->client->request('GET', '/objets', ['categorie' => $this->categorie('Hauts')->getId()]);

        // Du plus récent au plus ancien
        self::assertSame(['Chemise blanche', 'Pull marine'], $this->titres($crawler));
        self::assertSelectorTextContains('.filtre--actif', 'Hauts');
        // Le bouton Scanner présélectionne la catégorie filtrée
        self::assertSelectorExists('a[href="/objets/nouveau?categorie='.$this->categorie('Hauts')->getId().'"]');

        $crawler = $this->client->request('GET', '/objets', ['categorie' => 'aucune']);
        self::assertSame(['Boîte à couture'], $this->titres($crawler));
    }

    public function testRechercherDansLInventaire(): void
    {
        $crawler = $this->client->request('GET', '/objets', ['q' => 'sandro']);
        self::assertSame(['Chemise blanche'], $this->titres($crawler));

        $crawler = $this->client->request('GET', '/objets', ['q' => 'chaussures']);
        self::assertSame(['Baskets'], $this->titres($crawler));

        $this->client->request('GET', '/objets', ['q' => 'introuvable']);
        self::assertSelectorTextContains('.etat-vide__titre', 'Aucun objet ne correspond');
    }

    public function testLAccueilMontreCategoriesEtDerniersObjets(): void
    {
        $crawler = $this->client->request('GET', '/accueil');

        self::assertSelectorExists('form.recherche--accueil[action="/objets"]');
        self::assertSelectorTextContains('.statistique', '4');
        // Les catégories les plus fournies d'abord
        $puces = $crawler->filter('.section-accueil .filtre')->each(static fn ($n) => preg_replace('/\s+/', ' ', trim($n->text())));
        self::assertSame(['Hauts 2', 'Chaussures 1'], \array_slice($puces, 0, 2));
        self::assertCount(\count(Categorie::PREDEFINIES), $puces);
        self::assertCount(4, $crawler->filter('.section-accueil .liste-cartes .carte-ligne'));
    }

    public function testModifierLaFicheDUnObjet(): void
    {
        $objet = $this->em()->getRepository(Objet::class)->findOneBy(['nom' => 'Boîte à couture']);

        $crawler = $this->client->request('GET', '/objets/'.$objet->getId());
        $crawler = $this->client->click($crawler->selectLink('Modifier la fiche')->link());
        $this->client->submit($crawler->selectButton('Enregistrer')->form([
            'objet[type]' => 'Boîte',
            'objet[categorie]' => (string) $this->categorie('Déco')->getId(),
        ]));

        self::assertResponseRedirects('/objets/'.$objet->getId());
        $this->client->followRedirect();
        self::assertSelectorTextContains('.etiquette--categorie', 'Déco');
        self::assertSelectorTextContains('.caracteristiques', 'Boîte');
    }

    private function creerObjet(string $nom, ?string $categorie, ?string $marque): void
    {
        $objet = (new Objet($this->camille))
            ->setNom($nom)
            ->setMarque($marque)
            ->setCategorie(null === $categorie ? null : $this->categorie($categorie));
        $this->em()->persist($objet);
    }

    private function categorie(string $nom): Categorie
    {
        return $this->em()->getRepository(Categorie::class)->findOneBy(['nom' => $nom, 'proprietaire' => null]);
    }

    /**
     * @return list<string>
     */
    private function titres(Crawler $crawler): array
    {
        return $crawler->filter('.galerie .vignette')->each(
            static fn (Crawler $vignette) => trim($vignette->filter('.vignette__titre')->getNode(0)->firstChild->textContent),
        );
    }
}
