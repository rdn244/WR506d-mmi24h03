<?php

namespace App\Tests\Unitaire\Entity;

use App\Entity\Categorie;
use App\Entity\CompteInstagram;
use App\Entity\Contenant;
use App\Entity\Lien;
use App\Entity\Logement;
use App\Entity\Look;
use App\Entity\Objet;
use App\Entity\Piece;
use App\Entity\PublicationInstagram;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Enum\FormatPublication;
use App\Enum\NiveauSousCompte;
use App\Enum\TypeContenant;
use App\Enum\TypeLien;
use App\Enum\TypeLogement;
use App\Enum\TypePiece;
use PHPUnit\Framework\TestCase;

/**
 * Normalisation des saisies et valeurs dérivées des entités (nom complet, initiale, icône, légende…).
 */
class AccesseursTest extends TestCase
{
    public function testUnCompteNormaliseSonEmailEtCalculeSonIdentite(): void
    {
        $user = (new User())->setEmail('  Camille.Martin@Dressing.TEST ')->setPrenom('émilie')->setNom(null);

        self::assertSame('camille.martin@dressing.test', $user->getEmail());
        self::assertSame('camille.martin@dressing.test', $user->getUserIdentifier());
        self::assertSame('émilie', $user->getPrenom());
        self::assertNull($user->getNom());
        self::assertSame('émilie', $user->getNomComplet(), 'Sans nom de famille, pas d\'espace en trop');
        self::assertSame('É', $user->getInitiale(), 'Initiale en majuscule, accents compris');
        self::assertSame('Émilie Martin', (new User())->setPrenom('Émilie')->setNom('Martin')->getNomComplet());
    }

    public function testLaSessionNeContientJamaisLeHashDuMotDePasse(): void
    {
        $user = (new User())->setEmail('camille@dressing.test')->setPassword('$2y$13$hash-tres-secret');

        $session = $user->__serialize();

        self::assertNotContains('$2y$13$hash-tres-secret', $session);
        self::assertContains(hash('crc32c', '$2y$13$hash-tres-secret'), $session);
    }

    public function testUnObjetNettoieSesChamps(): void
    {
        $camille = new User();
        $categorie = Categorie::predefinie('Hauts', 'ph:t-shirt-duotone', 1);
        $contenant = new Contenant();
        $objet = (new Objet($camille))
            ->setNom('  Pull marine ')
            ->setMarque('')
            ->setType(' Pull ')
            ->setCategorie($categorie)
            ->setMatiere('Laine')
            ->setCouleur('   ')
            ->setPhoto('photo.jpg')
            ->setContenant($contenant)
            ->setPersonnel(true)
            ->setModeleIa(' google/gemma-4-31b-it:free ');

        self::assertNull($objet->getId());
        self::assertSame($camille, $objet->getProprietaire());
        self::assertSame('Pull marine', $objet->getNom());
        self::assertNull($objet->getMarque(), 'Une saisie vide devient null');
        self::assertSame('Pull', $objet->getType());
        self::assertSame($categorie, $objet->getCategorie());
        self::assertSame('Laine', $objet->getMatiere());
        self::assertNull($objet->getCouleur());
        self::assertSame('photo.jpg', $objet->getPhoto());
        self::assertSame($contenant, $objet->getContenant());
        self::assertTrue($objet->isPersonnel());
        self::assertSame('google/gemma-4-31b-it:free', $objet->getModeleIa());
        self::assertEqualsWithDelta(time(), $objet->getCreeLe()->getTimestamp(), 5);
        self::assertNull($categorie->getId());
    }

    public function testLaLegendeParDefautDUnLook(): void
    {
        $look = (new Look(new User()))->setTitre('  Tenue d\'été ')->setDescription('   ')->setPhoto('look.png');

        self::assertNull($look->getId());
        self::assertSame('Tenue d\'été', $look->getTitre());
        self::assertNull($look->getDescription());
        self::assertSame('Tenue d\'été', $look->getLegendeParDefaut(), 'Sans description, le titre sert de légende');
        self::assertSame('look.png', $look->getPhoto());
        self::assertEqualsWithDelta(time(), $look->getCreeLe()->getTimestamp(), 5);

        $look->setDescription('Manteau Sandro, taille M');
        self::assertSame('Manteau Sandro, taille M', $look->getLegendeParDefaut());
        self::assertNull((new Look(new User()))->setTitre(null)->getTitre());
    }

    public function testUnSousCompteMajeurNAPasDeNiveau(): void
    {
        $leo = (new SousCompte())->setPrenom('léo')->setDateNaissance(new \DateTimeImmutable('-19 years'))->setNiveau(NiveauSousCompte::Simplifie);
        $emma = (new SousCompte())->setPrenom('Emma')->setDateNaissance(new \DateTimeImmutable('-9 years'))->setNiveau(NiveauSousCompte::VueLimitee);

        self::assertNull($leo->getId());
        self::assertSame('léo', $leo->getPrenom());
        self::assertSame('L', $leo->getInitiale());
        self::assertNull($leo->getNiveau(), 'Le niveau ne concerne que les enfants');
        self::assertSame('Sous-compte +18', $leo->getLibelleType());
        self::assertSame(NiveauSousCompte::VueLimitee, $emma->getNiveau());
        self::assertSame('Sous-compte enfant', $emma->getLibelleType());
        self::assertSame((new \DateTimeImmutable('-9 years'))->format('Y-m-d'), $emma->getDateNaissance()?->format('Y-m-d'));
        // Sans date de naissance, on ne suppose jamais la majorité
        self::assertFalse((new SousCompte())->estMajeur());
        // La majorité se calcule à la date demandée : la veille des 18 ans, l'enfant est encore mineur
        $ne = (new SousCompte())->setDateNaissance(new \DateTimeImmutable('2008-06-15'));
        self::assertFalse($ne->estMajeur(new \DateTimeImmutable('2026-06-14')));
        self::assertTrue($ne->estMajeur(new \DateTimeImmutable('2026-06-15')));
    }

    public function testLAutreMembreDUnLien(): void
    {
        $camille = new User();
        $sophie = new User();
        $lien = new Lien(TypeLien::Ami, $sophie, $camille);

        self::assertSame($camille, $lien->getDestinataire());
        self::assertSame($sophie, $lien->getDemandeur());
        self::assertSame($camille, $lien->autreQue($sophie));
        self::assertSame($sophie, $lien->autreQue($camille));
    }

    public function testLogementPieceEtRangement(): void
    {
        $camille = new User();
        $logement = (new Logement())->setNom('Appartement de Lyon')->setType(TypeLogement::Appartement)->setAdresse('Lyon');
        $camille->addLogement($logement);
        $piece = Piece::depuisType(TypePiece::Dressing)->setPartagee(true);
        $logement->addPiece($piece);
        $contenant = (new Contenant())->setNom('Penderie')->setType(null)->setPartage(true);
        $piece->addContenant($contenant);

        self::assertNull($logement->getId());
        self::assertSame('Appartement de Lyon', $logement->getNom());
        self::assertSame(TypeLogement::Appartement, $logement->getType());
        self::assertSame('Lyon', $logement->getAdresse());
        self::assertSame($camille, $logement->getProprietaire());
        self::assertNull($piece->getId());
        self::assertSame('Dressing', $piece->getNom(), 'Une pièce prédéfinie prend le libellé de son type');
        self::assertSame(TypePiece::Dressing->icone(), $piece->getIcone());
        self::assertTrue($piece->isPartagee());
        self::assertSame($logement, $piece->getLogement());
        self::assertNull($contenant->getId());
        self::assertSame('Penderie', $contenant->getNom());
        self::assertNull($contenant->getType());
        self::assertSame(TypeContenant::Autre->icone(), $contenant->getIcone(), 'Sans type, l\'icône « Autre » sert de repli');
        self::assertTrue($contenant->isPartage());
        self::assertSame($piece, $contenant->getPiece());
    }

    public function testCompteEtPublicationInstagram(): void
    {
        $camille = new User();
        $compte = new CompteInstagram($camille, 'compte-1', 'camille dressing');
        $publication = new PublicationInstagram(new Look($camille), FormatPublication::Feed, null, 'post-1', 'https://www.instagram.com/p/abc/');

        self::assertSame($camille, $compte->getUtilisateur());
        self::assertSame('compte-1', $compte->getZernioId());
        self::assertSame('https://www.instagram.com/camille%20dressing/', $compte->getUrlProfil(), 'Le pseudo est encodé dans l\'URL');
        self::assertEqualsWithDelta(time(), $compte->getConnecteLe()->getTimestamp(), 5);

        $compte->mettreAJour('camille.style');
        self::assertSame('camille.style', $compte->getNomUtilisateur());

        self::assertSame(FormatPublication::Feed, $publication->getFormat());
        self::assertSame('post-1', $publication->getZernioId());
        self::assertSame('https://www.instagram.com/p/abc/', $publication->getUrl());
        self::assertEqualsWithDelta(time(), $publication->getPublieeLe()->getTimestamp(), 5);
    }
}
