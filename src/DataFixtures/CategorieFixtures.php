<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

/**
 * Les 12 catégories prédéfinies : le chargement des fixtures vide la table remplie par la migration.
 */
class CategorieFixtures extends Fixture
{
    public static function reference(string $nom): string
    {
        return 'categorie-'.$nom;
    }

    public function load(ObjectManager $manager): void
    {
        $ordre = 0;
        foreach (Categorie::PREDEFINIES as $nom => $icone) {
            $categorie = Categorie::predefinie($nom, $icone, ++$ordre);
            $manager->persist($categorie);
            $this->addReference(self::reference($nom), $categorie);
        }

        $manager->flush();
    }
}
