<?php

namespace App\Tests\Unitaire\Entity;

use App\Entity\Categorie;
use App\Entity\CompteInstagram;
use App\Entity\Contenant;
use App\Entity\Lien;
use App\Entity\Logement;
use App\Entity\Look;
use App\Entity\Piece;
use App\Entity\PublicationInstagram;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Enum\FormatPublication;
use App\Enum\StatutLien;
use App\Enum\TypeLien;
use App\Enum\TypePiece;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\TestCase;

/**
 * Règles portées par les entités elles-mêmes (sans base de données).
 */
class EntitesTest extends TestCase
{
    public function testUneCategoriePredefinieNAPasDeProprietaire(): void
    {
        $categorie = Categorie::predefinie('Hauts', 'ph:t-shirt-duotone', 1);

        self::assertTrue($categorie->estPredefinie());
        self::assertNull($categorie->getProprietaire());
        self::assertSame(1, $categorie->getOrdre());
        self::assertSame('ph:t-shirt-duotone', $categorie->getIcone());
        self::assertEqualsWithDelta(time(), $categorie->getCreeLe()->getTimestamp(), 5);
        self::assertFalse((new Categorie(new User()))->estPredefinie());
    }

    public function testLeNomDUneCategorieEstNormalise(): void
    {
        self::assertSame('Tenues de sport', (new Categorie())->setNom("  Tenues \t de   sport ")->getNom());
        self::assertNull((new Categorie())->setNom('   ')->getNom());
        self::assertNull((new Categorie())->setNom(null)->getNom());
    }

    public function testUneDemandeDAmiAttendLAcceptation(): void
    {
        $lien = new Lien(TypeLien::Ami, new User(), new User());

        self::assertNull($lien->getId());
        self::assertSame(TypeLien::Ami, $lien->getType());
        self::assertSame(StatutLien::EnAttente, $lien->getStatut());
        self::assertFalse($lien->estAccepte());

        $lien->accepter();

        self::assertTrue($lien->estAccepte());
        self::assertEqualsWithDelta(time(), $lien->getCreeLe()->getTimestamp(), 5);
    }

    public function testUnAbonnementEstImmediat(): void
    {
        self::assertSame(StatutLien::Accepte, (new Lien(TypeLien::Abonnement, new User(), new User()))->getStatut());
    }

    public function testLogementPiecesEtRangements(): void
    {
        $logement = (new Logement())->setPrive(true);
        $piece = Piece::depuisType(TypePiece::Grenier);
        $contenant = (new Contenant())->setNom('Carton');
        $logement->addPiece($piece);
        $piece->addContenant($contenant);

        self::assertTrue($logement->isPrive());
        self::assertSame(TypePiece::Grenier, $piece->getType());
        self::assertEqualsWithDelta(time(), $logement->getCreeLe()->getTimestamp(), 5);

        $piece->removeContenant($contenant);
        $logement->removePiece($piece);

        self::assertCount(0, $piece->getContenants());
        self::assertCount(0, $logement->getPieces());
    }

    public function testUnePublicationSAjouteAuLook(): void
    {
        $look = new Look(new User());
        $publication = new PublicationInstagram($look, FormatPublication::Story, 'Ma story', 'post-1', null);

        self::assertNull($publication->getId());
        self::assertSame($look, $publication->getLook());
        self::assertSame('Ma story', $publication->getLegende());
        self::assertSame([$publication], $look->getPublications()->toArray());
        self::assertNull((new CompteInstagram(new User(), 'compte-1', 'camille'))->getId());
    }

    public function testRolesEtCollectionsDuCompte(): void
    {
        $user = (new User())->setRoles(['ROLE_ADMIN', 'ROLE_USER']);
        $sousCompte = new SousCompte();
        $logement = new Logement();
        $user->addSousCompte($sousCompte)->addLogement($logement);

        self::assertSame(['ROLE_ADMIN', 'ROLE_USER'], $user->getRoles(), 'ROLE_USER n\'est jamais en double');
        self::assertEqualsWithDelta(time(), $user->getCreeLe()->getTimestamp(), 5);

        $user->removeSousCompte($sousCompte)->removeLogement($logement);

        self::assertCount(0, $user->getSousComptes());
        self::assertCount(0, $user->getLogements());
    }

    #[IgnoreDeprecations]
    public function testEffacerLesIdentifiantsEstDeprecie(): void
    {
        $this->expectUserDeprecationMessageMatches('/eraseCredentials\(\) is deprecated/');

        (new User())->eraseCredentials();
    }
}
