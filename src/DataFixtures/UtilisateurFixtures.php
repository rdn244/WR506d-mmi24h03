<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\Lien;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Enum\NiveauSousCompte;
use App\Enum\TypeLien;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Comptes de la campagne de test, avec toutes les relations de la matrice des droits :
 * Camille (propriétaire), Sophie (amie), Alex (compte lié), Marc (abonné), Nina (sans lien),
 * Bruno (bloqué par Camille), et les sous-comptes de Camille : Léo (majeur) et Emma (enfant).
 */
class UtilisateurFixtures extends Fixture
{
    /**
     * Mot de passe commun à tous les comptes de test (jamais utilisé hors de l'environnement local).
     */
    public const MOT_DE_PASSE = 'motdepasse123';

    public const CAMILLE = 'camille@dressing.test';
    public const SOPHIE = 'sophie@dressing.test';
    public const ALEX = 'alex@dressing.test';
    public const MARC = 'marc@dressing.test';
    public const NINA = 'nina@dressing.test';
    public const BRUNO = 'bruno@dressing.test';

    public const CATEGORIE_SPORT = 'categorie-perso-sport';

    public function __construct(
        private readonly UserPasswordHasherInterface $hasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $camille = $this->creer($manager, self::CAMILLE, 'Camille', 'Martin');
        $sophie = $this->creer($manager, self::SOPHIE, 'Sophie', 'Bernard');
        $alex = $this->creer($manager, self::ALEX, 'Alex', 'Martin');
        $marc = $this->creer($manager, self::MARC, 'Marc', 'Petit', comptePublic: true);
        $this->creer($manager, self::NINA, 'Nina', 'Robert');
        $bruno = $this->creer($manager, self::BRUNO, 'Bruno', 'Durand');

        // Relations envers Camille : une par colonne de la matrice
        $manager->persist((new Lien(TypeLien::Ami, $sophie, $camille))->accepter());
        $manager->persist((new Lien(TypeLien::CompteLie, $alex, $camille))->accepter());
        $manager->persist(new Lien(TypeLien::Abonnement, $marc, $camille));
        $manager->persist(new Lien(TypeLien::Blocage, $camille, $bruno));

        $leo = (new SousCompte())->setPrenom('Léo')->setDateNaissance(new \DateTimeImmutable('-19 years'));
        $emma = (new SousCompte())->setPrenom('Emma')->setDateNaissance(new \DateTimeImmutable('-9 years'))->setNiveau(NiveauSousCompte::Simplifie);
        foreach ([$leo, $emma] as $sousCompte) {
            $camille->addSousCompte($sousCompte);
            $manager->persist($sousCompte);
        }

        $sport = (new Categorie($camille))->setNom('Sport')->setIcone('ph:basketball-duotone');
        $manager->persist($sport);
        $this->addReference(self::CATEGORIE_SPORT, $sport);

        $manager->flush();
    }

    private function creer(ObjectManager $manager, string $email, string $prenom, string $nom, bool $comptePublic = false): User
    {
        $user = (new User())->setEmail($email)->setPrenom($prenom)->setNom($nom)->setComptePublic($comptePublic);
        $user->setPassword($this->hasher->hashPassword($user, self::MOT_DE_PASSE));
        $manager->persist($user);
        $this->addReference($email, $user);

        return $user;
    }
}
