<?php

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Tests d'intégration : vrais services du conteneur et vraie base (penderie_test), sans HTTP.
 */
abstract class IntegrationTestCase extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
        // Tout ce qui appartient à un compte disparaît en cascade
        $this->em()->getConnection()->executeStatement('DELETE FROM utilisateur');
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    /**
     * Charge les fixtures (src/DataFixtures) dans la base de test : comptes, liens, logement, inventaire, looks.
     */
    protected function chargerFixtures(): void
    {
        $commande = new CommandTester((new Application(self::$kernel))->find('doctrine:fixtures:load'));
        $commande->execute([], ['interactive' => false]);
        $commande->assertCommandIsSuccessful();
    }

    protected function compte(string $email): User
    {
        $user = $this->em()->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertNotNull($user, $email);

        return $user;
    }

    protected function membre(string $prenom): User
    {
        $user = (new User())->setEmail(strtolower($prenom).'@integration.test')->setPrenom($prenom)->setPassword('hash');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }
}
