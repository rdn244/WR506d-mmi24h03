<?php

namespace App\Tests\Integration\Entity;

use App\Entity\SousCompte;
use App\Enum\NiveauSousCompte;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Validation d'un sous-compte avec le vrai validateur de l'application : un mineur doit avoir un niveau.
 */
class SousCompteValidationTest extends KernelTestCase
{
    public function testUnMineurSansNiveauEstRefuse(): void
    {
        // Le niveau vaut « Simplifié » par défaut : seul un formulaire qui le vide déclenche la règle
        $emma = (new SousCompte())->setPrenom('Emma')->setDateNaissance(new \DateTimeImmutable('-9 years'))->setNiveau(null);

        $violations = $this->validateur()->validate($emma);

        self::assertCount(1, $violations);
        self::assertSame('niveau', $violations[0]->getPropertyPath());
        self::assertSame('Choisis les permissions du profil.', $violations[0]->getMessage());
        self::assertEqualsWithDelta(time(), $emma->getCreeLe()->getTimestamp(), 5);
    }

    public function testUnMineurAvecNiveauOuUnMajeurSontAcceptes(): void
    {
        $emma = (new SousCompte())->setPrenom('Emma')->setDateNaissance(new \DateTimeImmutable('-9 years'))->setNiveau(NiveauSousCompte::Simplifie);
        $leo = (new SousCompte())->setPrenom('Léo')->setDateNaissance(new \DateTimeImmutable('-19 years'));

        self::assertCount(0, $this->validateur()->validate($emma));
        self::assertCount(0, $this->validateur()->validate($leo));
    }

    private function validateur(): ValidatorInterface
    {
        return static::getContainer()->get(ValidatorInterface::class);
    }
}
