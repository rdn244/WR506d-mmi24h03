<?php

namespace App\Tests\Controller;

use App\Entity\Logement;
use App\Entity\Piece;
use App\Enum\TypeContenant;
use App\Enum\TypePiece;
use App\Repository\LogementRepository;

class LogementTest extends AppWebTestCase
{
    public function testMonLogementRedirigeVersLaCreationSiAucunLogement(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/logement');

        self::assertResponseRedirects('/logements/nouveau');
    }

    public function testParcoursCreationLogementEtPieces(): void
    {
        $this->client->loginUser($this->creerUtilisateur());

        $this->client->request('GET', '/logements/nouveau');
        $this->client->submitForm('Continuer', [
            'logement[nom]' => 'Maison de Troyes',
            'logement[type]' => 'appartement',
            'logement[adresse]' => 'Troyes, 10000',
        ]);

        $logement = static::getContainer()->get(LogementRepository::class)->findOneBy(['nom' => 'Maison de Troyes']);
        self::assertInstanceOf(Logement::class, $logement);
        self::assertResponseRedirects('/logements/'.$logement->getId().'/pieces');

        // Les pièces de la maquette sont pré-cochées au premier passage
        $crawler = $this->client->followRedirect();
        self::assertCount(5, $crawler->filter('.carte-choix__entree:checked'));

        $form = $crawler->selectButton('Terminer et découvrir mon dressing')->form();
        $form['pieces[types]'][6]->tick(); // Bureau
        $valeurs = $form->getPhpValues();
        $valeurs['pieces']['personnalisees'] = ['Buanderie', ''];
        $this->client->request('POST', $form->getUri(), $valeurs);

        self::assertResponseRedirects('/logements/'.$logement->getId());
        $crawler = $this->client->followRedirect();

        self::assertSelectorTextContains('h1', 'Maison de Troyes');
        self::assertSelectorTextContains('.badge', 'Privé');
        self::assertSame(
            ['Chambre', 'Salon', 'Cuisine', 'Grenier', 'Bureau', 'Dressing', 'Buanderie'],
            $crawler->filter('.carte-ligne__titre')->each(static fn ($noeud) => $noeud->text()),
        );
    }

    public function testAuMoinsUnePieceEstObligatoire(): void
    {
        $user = $this->creerUtilisateur();
        $logement = $this->creerLogement($user);
        $this->client->loginUser($user);

        $crawler = $this->client->request('GET', '/logements/'.$logement->getId().'/pieces');
        $form = $crawler->selectButton('Terminer et découvrir mon dressing')->form();
        foreach ($form['pieces[types]'] as $case) {
            $case->untick();
        }
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs', 'Sélectionne au moins une pièce.');
    }

    public function testAjoutDUnContenantDansUnePiece(): void
    {
        $user = $this->creerUtilisateur();
        $logement = $this->creerLogement($user);
        $piece = Piece::depuisType(TypePiece::Chambre);
        $logement->addPiece($piece);
        $this->em()->flush();
        $this->client->loginUser($user);

        $this->client->request('GET', '/pieces/'.$piece->getId());
        self::assertSelectorTextContains('.retour', 'Maison de Troyes');
        self::assertSelectorTextContains('.vide', 'Aucun contenant');

        $this->client->submitForm('Ajouter', [
            'contenant[nom]' => 'Armoire',
            'contenant[type]' => TypeContenant::Armoire->value,
        ]);
        self::assertResponseRedirects('/pieces/'.$piece->getId());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.carte-ligne__titre', 'Armoire');
        self::assertSelectorTextContains('.sous-titre', '1 contenant');
    }

    public function testUnUtilisateurNAccedePasAuLogementDUnAutre(): void
    {
        $proprietaire = $this->creerUtilisateur();
        $logement = $this->creerLogement($proprietaire);
        $piece = Piece::depuisType(TypePiece::Salon);
        $logement->addPiece($piece);
        $this->em()->flush();

        $this->client->loginUser($this->creerUtilisateur('intrus@example.com', 'Intrus'));

        foreach ([
            '/logements/'.$logement->getId(),
            '/logements/'.$logement->getId().'/pieces',
            '/pieces/'.$piece->getId(),
        ] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
    }
}
