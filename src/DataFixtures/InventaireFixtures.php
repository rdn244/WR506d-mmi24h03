<?php

namespace App\DataFixtures;

use App\Entity\Categorie;
use App\Entity\Contenant;
use App\Entity\Logement;
use App\Entity\Objet;
use App\Entity\Piece;
use App\Entity\User;
use App\Enum\TypeContenant;
use App\Enum\TypeLogement;
use App\Enum\TypePiece;
use App\Photo\PhotoBase64;
use App\Photo\StockagePhotos;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Logement de Camille (3 pièces, 4 rangements) et son inventaire : objets dans plusieurs catégories,
 * avec et sans photo, rangés ou non, un objet personnel et un objet sans catégorie.
 */
class InventaireFixtures extends Fixture implements DependentFixtureInterface
{
    /**
     * nom, marque, type, catégorie, matière, couleur, photo, rangement, personnel
     */
    private const OBJETS = [
        ['Manteau bordeaux', 'Sandro', 'Manteau', 'Vestes et manteaux', 'Laine', 'Bordeaux', 'manteau-sandro', 'Penderie', false],
        ['T-shirt Nike', 'Nike', 'T-shirt', 'Hauts', 'Coton', 'Rouge', 'tshirt-nike', 'Commode', false],
        ['Pull marine', 'Petit Bateau', 'Pull', 'Hauts', 'Laine', 'Marine', null, 'Commode', false],
        ['Jean brut', 'Levi\'s', 'Jean', 'Bas', 'Denim', 'Bleu', 'jean-levis', 'Armoire', false],
        ['Robe d\'été', null, 'Robe', 'Robes', 'Lin', 'Ocre', 'robe-ete', 'Penderie', false],
        ['Baskets blanches', 'Veja', 'Baskets', 'Chaussures', 'Cuir', 'Blanc', 'baskets-veja', null, false],
        ['Sac en cuir', null, 'Sac', 'Sacs', 'Cuir', 'Camel', 'sac-cuir', 'Armoire', false],
        ['Nevermind', 'Nirvana', 'Vinyle', 'Musique', null, 'Bleu', null, 'Carton du grenier', false],
        ['Short de running', 'Kalenji', 'Short', UtilisateurFixtures::CATEGORIE_SPORT, 'Polyester', 'Noir', null, 'Commode', false],
        ['Journal intime', null, 'Carnet', 'Livres', 'Papier', 'Rose', null, 'Commode', true],
        ['Boîte à couture', null, 'Boîte', null, null, null, null, 'Carton du grenier', false],
    ];

    public function __construct(
        private readonly StockagePhotos $photosObjets,
    ) {
    }

    public function getDependencies(): array
    {
        return [CategorieFixtures::class, UtilisateurFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        $camille = $this->getReference(UtilisateurFixtures::CAMILLE, User::class);

        $logement = (new Logement())->setNom('Appartement de Lyon')->setType(TypeLogement::Appartement)->setAdresse('Lyon');
        $camille->addLogement($logement);

        $rangements = [];
        foreach ([
            [TypePiece::Chambre, false, [['Armoire', TypeContenant::Armoire, false], ['Commode', TypeContenant::Commode, true]]],
            [TypePiece::Dressing, true, [['Penderie', TypeContenant::Armoire, false]]],
            [TypePiece::Grenier, false, [['Carton du grenier', TypeContenant::Boite, false]]],
        ] as [$type, $partagee, $contenants]) {
            $piece = Piece::depuisType($type)->setPartagee($partagee);
            $logement->addPiece($piece);
            foreach ($contenants as [$nom, $typeContenant, $partage]) {
                $contenant = (new Contenant())->setNom($nom)->setType($typeContenant)->setPartage($partage);
                $piece->addContenant($contenant);
                $rangements[$nom] = $contenant;
            }
        }
        $manager->persist($logement);

        foreach (self::OBJETS as [$nom, $marque, $type, $categorie, $matiere, $couleur, $photo, $rangement, $personnel]) {
            $objet = (new Objet($camille))
                ->setNom($nom)
                ->setMarque($marque)
                ->setType($type)
                ->setCategorie(null === $categorie ? null : $this->categorie($categorie))
                ->setMatiere($matiere)
                ->setCouleur($couleur)
                ->setContenant(null === $rangement ? null : $rangements[$rangement])
                ->setPersonnel($personnel)
                ->setPhoto(null === $photo ? null : $this->photosObjets->enregistrerContenu(self::photo($photo)));
            $manager->persist($objet);
        }

        $manager->flush();
    }

    /**
     * Photo dessinée pour les fixtures (src/DataFixtures/photos), validée comme une photo de scan.
     */
    public static function photo(string $nom): PhotoBase64
    {
        return PhotoBase64::depuisDataUrl('data:image/png;base64,'.base64_encode((string) file_get_contents(__DIR__.'/photos/'.$nom.'.png')));
    }

    private function categorie(string $nomOuReference): Categorie
    {
        $reference = UtilisateurFixtures::CATEGORIE_SPORT === $nomOuReference ? $nomOuReference : CategorieFixtures::reference($nomOuReference);

        return $this->getReference($reference, Categorie::class);
    }
}
