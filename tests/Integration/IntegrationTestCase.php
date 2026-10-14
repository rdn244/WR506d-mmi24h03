<?php

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

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

    protected function membre(string $prenom): User
    {
        $user = (new User())->setEmail(strtolower($prenom).'@integration.test')->setPrenom($prenom)->setPassword('hash');
        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }
}
