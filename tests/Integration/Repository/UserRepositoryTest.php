<?php

namespace App\Tests\Integration\Repository;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Tests\Integration\IntegrationTestCase;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\InMemoryUser;

/**
 * Re-hachage automatique du mot de passe quand l'algorithme évolue.
 */
class UserRepositoryTest extends IntegrationTestCase
{
    public function testLeNouveauHashEstEnregistre(): void
    {
        $camille = $this->membre('Camille');

        static::getContainer()->get(UserRepository::class)->upgradePassword($camille, 'nouveau-hash');
        $this->em()->clear();

        self::assertSame('nouveau-hash', $this->em()->find(User::class, $camille->getId())?->getPassword());
    }

    public function testUnAutreTypeDUtilisateurEstRefuse(): void
    {
        $this->expectException(UnsupportedUserException::class);

        static::getContainer()->get(UserRepository::class)->upgradePassword(new InMemoryUser('admin', 'x'), 'hash');
    }
}
