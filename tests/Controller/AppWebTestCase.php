<?php

namespace App\Tests\Controller;

use App\Entity\Logement;
use App\Entity\User;
use App\Enum\TypeLogement;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

abstract class AppWebTestCase extends WebTestCase
{
    protected const MOT_DE_PASSE = 'motdepasse123';

    protected KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        // Les logements, pièces et contenants sont supprimés en cascade
        $this->em()->getConnection()->executeStatement('DELETE FROM utilisateur');
    }

    protected function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    protected function creerUtilisateur(string $email = 'lea@example.com', string $prenom = 'Léa'): User
    {
        $user = (new User())->setEmail($email)->setPrenom($prenom);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::MOT_DE_PASSE));

        $this->em()->persist($user);
        $this->em()->flush();

        return $user;
    }

    protected function creerLogement(User $proprietaire, string $nom = 'Maison de Troyes'): Logement
    {
        $logement = (new Logement())->setNom($nom)->setType(TypeLogement::Maison);
        $proprietaire->addLogement($logement);

        $this->em()->persist($logement);
        $this->em()->flush();

        return $logement;
    }
}
