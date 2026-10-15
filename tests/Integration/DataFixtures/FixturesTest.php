<?php

namespace App\Tests\Integration\DataFixtures;

use App\DataFixtures\UtilisateurFixtures;
use App\Droits\Acteur;
use App\Droits\ControleAcces;
use App\Droits\Droit;
use App\Entity\Categorie;
use App\Entity\Look;
use App\Entity\Objet;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Les fixtures de la campagne de test : elles se chargent et couvrent chaque relation de la matrice.
 */
class FixturesTest extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerFixtures();
    }

    public function testLesComptesSontUtilisablesAvecLeMotDePasseDeTest(): void
    {
        $hacheur = static::getContainer()->get(UserPasswordHasherInterface::class);

        foreach ([UtilisateurFixtures::CAMILLE, UtilisateurFixtures::SOPHIE, UtilisateurFixtures::ALEX, UtilisateurFixtures::MARC, UtilisateurFixtures::NINA, UtilisateurFixtures::BRUNO] as $email) {
            $user = $this->compte($email);
            self::assertTrue($hacheur->isPasswordValid($user, UtilisateurFixtures::MOT_DE_PASSE), $email);
        }
        self::assertCount(2, $this->compte(UtilisateurFixtures::CAMILLE)->getSousComptes());
    }

    public function testLInventaireEtLesCategoriesSontEnPlace(): void
    {
        self::assertSame(\count(Categorie::PREDEFINIES) + 1, $this->em()->getRepository(Categorie::class)->count([]));
        self::assertSame(11, $this->em()->getRepository(Objet::class)->count([]));
        self::assertSame(2, $this->em()->getRepository(Look::class)->count([]));
        self::assertNotNull($this->em()->getRepository(Objet::class)->findOneBy(['personnel' => true]));
        self::assertNotNull($this->em()->getRepository(Objet::class)->findOneBy(['categorie' => null]));
    }

    public function testChaqueRelationDonneLesDroitsAttendusSurUnLook(): void
    {
        $controle = static::getContainer()->get(ControleAcces::class);
        $look = $this->em()->getRepository(Look::class)->findOneBy([]);
        self::assertNotNull($look);

        $attendus = [
            UtilisateurFixtures::CAMILLE => Droit::Modifier,
            UtilisateurFixtures::SOPHIE => Droit::Voir,
            UtilisateurFixtures::ALEX => Droit::Voir,
            UtilisateurFixtures::MARC => Droit::Voir,
            UtilisateurFixtures::NINA => Droit::Rien,
            UtilisateurFixtures::BRUNO => Droit::Rien,
        ];
        foreach ($attendus as $email => $droit) {
            self::assertSame($droit, $controle->droitSur(new Acteur($this->compte($email)), $look), $email);
        }
    }
}
