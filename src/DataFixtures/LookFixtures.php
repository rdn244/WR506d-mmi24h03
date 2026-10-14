<?php

namespace App\DataFixtures;

use App\Entity\Look;
use App\Entity\User;
use App\Photo\StockagePhotos;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Deux looks de Camille, avec photo : visibles par ses proches, publiables sur Instagram.
 */
class LookFixtures extends Fixture implements DependentFixtureInterface
{
    private const LOOKS = [
        ['Manteau bordeaux et jean brut', 'Nouveau dans ma vitrine : manteau Sandro, taille M.', 'manteau-sandro'],
        ['Tenue d\'été', null, 'robe-ete'],
    ];

    public function __construct(
        private readonly StockagePhotos $photosLooks,
    ) {
    }

    public function getDependencies(): array
    {
        return [UtilisateurFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $camille = $this->getReference(UtilisateurFixtures::CAMILLE, User::class);

        foreach (self::LOOKS as [$titre, $description, $photo]) {
            $look = (new Look($camille))
                ->setTitre($titre)
                ->setDescription($description)
                ->setPhoto($this->photosLooks->enregistrerContenu(InventaireFixtures::photo($photo)));
            $manager->persist($look);
        }

        $manager->flush();
    }
}
