<?php

namespace App\Tests\Integration\Droits;

use App\Droits\GestionLiens;
use App\Entity\User;
use App\Enum\TypeLien;
use App\Repository\LienRepository;
use App\Tests\Integration\IntegrationTestCase;

/**
 * Règles des liens entre membres, sur la vraie base.
 */
class GestionLiensTest extends IntegrationTestCase
{
    private GestionLiens $liens;
    private LienRepository $depot;
    private User $camille;
    private User $sophie;

    protected function setUp(): void
    {
        parent::setUp();
        $this->liens = static::getContainer()->get(GestionLiens::class);
        $this->depot = static::getContainer()->get(LienRepository::class);
        $this->camille = $this->membre('Camille');
        $this->sophie = $this->membre('Sophie');
    }

    public function testUneDemandeCroiseeEstAccepteeDOffice(): void
    {
        self::assertFalse($this->liens->demander(TypeLien::Ami, $this->camille, $this->sophie)->estAccepte());

        $lien = $this->liens->demander(TypeLien::Ami, $this->sophie, $this->camille);

        self::assertTrue($lien->estAccepte());
        self::assertCount(1, $this->depot->findAll());
    }

    public function testUneDemandeEnDoubleEstRefusee(): void
    {
        $this->liens->demander(TypeLien::CompteLie, $this->camille, $this->sophie);

        $this->expectExceptionObject(new \DomainException('Ta demande est déjà envoyée.'));
        $this->liens->demander(TypeLien::CompteLie, $this->camille, $this->sophie);
    }

    public function testDejaAmisOuDejaLies(): void
    {
        $this->liens->demander(TypeLien::Ami, $this->camille, $this->sophie);
        $this->liens->accepter(TypeLien::Ami, $this->sophie, $this->camille);

        try {
            $this->liens->demander(TypeLien::Ami, $this->sophie, $this->camille);
            self::fail('Une deuxième demande d\'ami aurait dû être refusée.');
        } catch (\DomainException $e) {
            self::assertSame('Vous êtes déjà amis.', $e->getMessage());
        }

        $this->liens->demander(TypeLien::CompteLie, $this->camille, $this->sophie);
        $this->liens->accepter(TypeLien::CompteLie, $this->sophie, $this->camille);
        $this->expectExceptionObject(new \DomainException('Vos comptes sont déjà liés.'));
        $this->liens->demander(TypeLien::CompteLie, $this->sophie, $this->camille);
    }

    public function testAccepterUneDemandeDisparue(): void
    {
        $this->expectExceptionObject(new \DomainException('Cette demande n\'existe plus.'));

        $this->liens->accepter(TypeLien::Ami, $this->sophie, $this->camille);
    }

    public function testRetirerUnAmiDansLesDeuxSens(): void
    {
        $this->liens->demander(TypeLien::Ami, $this->camille, $this->sophie);

        $this->liens->retirer(TypeLien::Ami, $this->sophie, $this->camille);
        // Rien à retirer : sans effet
        $this->liens->retirer(TypeLien::Ami, $this->sophie, $this->camille);

        self::assertCount(0, $this->depot->findAll());
    }

    public function testSAbonnerUneSeuleFoisPuisSeDesabonner(): void
    {
        $this->liens->sAbonner($this->sophie, $this->camille);
        $this->liens->sAbonner($this->sophie, $this->camille);
        self::assertNotNull($this->depot->trouver(TypeLien::Abonnement, $this->sophie, $this->camille));
        self::assertCount(1, $this->depot->findAll());

        $this->liens->seDesabonner($this->sophie, $this->camille);
        $this->liens->seDesabonner($this->sophie, $this->camille);

        self::assertCount(0, $this->depot->findAll());
    }

    public function testBloquerSupprimeLesAutresLiensEtEmpecheLesDemandes(): void
    {
        $this->liens->demander(TypeLien::Ami, $this->camille, $this->sophie);
        $this->liens->sAbonner($this->camille, $this->sophie);

        $this->liens->bloquer($this->sophie, $this->camille);
        $this->liens->bloquer($this->sophie, $this->camille);

        self::assertCount(1, $this->depot->findAll(), 'Seul le blocage reste, une seule fois');
        try {
            $this->liens->sAbonner($this->camille, $this->sophie);
            self::fail('Un membre bloqué ne peut pas s\'abonner.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('bloqué', $e->getMessage());
        }

        $this->liens->debloquer($this->sophie, $this->camille);
        $this->liens->debloquer($this->sophie, $this->camille);
        $this->liens->sAbonner($this->camille, $this->sophie);

        self::assertNotNull($this->depot->trouver(TypeLien::Abonnement, $this->camille, $this->sophie));
    }

    public function testOnNeSeLieNiNeSeBloqueSoiMeme(): void
    {
        try {
            $this->liens->demander(TypeLien::Ami, $this->camille, $this->camille);
            self::fail('Une demande à soi-même aurait dû être refusée.');
        } catch (\DomainException $e) {
            self::assertStringContainsString('ton propre compte', $e->getMessage());
        }

        $this->expectExceptionObject(new \DomainException('Tu ne peux pas te bloquer toi-même.'));
        $this->liens->bloquer($this->camille, $this->camille);
    }
}
