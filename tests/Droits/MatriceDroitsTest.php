<?php

namespace App\Tests\Droits;

use App\Droits\Droit;
use App\Droits\MatriceDroits;
use App\Droits\Relation;
use App\Droits\Ressource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Vérifie chaque cellule de la matrice « Qui a le droit de voir ? » (séance 8).
 */
class MatriceDroitsTest extends TestCase
{
    /**
     * Recopie de la matrice : propriétaire, sous-compte +18, sous-compte enfant, compte lié, ami, abonné, inconnu.
     * M = MODIFIER, V = VOIR, R = RIEN (cas non conditionnels).
     */
    private const MATRICE = [
        'objet' => 'M M V M V R R',
        'objet_personnel' => 'M R R R R R R',
        'piece' => 'M M V R V R R',
        'look' => 'M V V V V V R',
        'item_look' => 'M V V V V V R',
        'pret' => 'M V V M V R R',
        'vente' => 'M V V V V V V',
        'categorie' => 'M M V M R R R',
        'rangement' => 'M M M M R R R',
        'rangement_partage' => 'M M V M V V R',
    ];

    private const COLONNES = [
        Relation::Proprietaire,
        Relation::SousCompteMajeur,
        Relation::SousCompteEnfant,
        Relation::CompteLie,
        Relation::Ami,
        Relation::Abonne,
        Relation::Inconnu,
    ];

    /**
     * @return iterable<string, array{Ressource, Relation, Droit}>
     */
    public static function cellules(): iterable
    {
        $droits = ['M' => Droit::Modifier, 'V' => Droit::Voir, 'R' => Droit::Rien];

        foreach (self::MATRICE as $ressource => $ligne) {
            foreach (explode(' ', $ligne) as $i => $code) {
                $relation = self::COLONNES[$i];
                yield $ressource.' / '.$relation->value => [Ressource::from($ressource), $relation, $droits[$code]];
            }
        }
    }

    #[DataProvider('cellules')]
    public function testCellule(Ressource $ressource, Relation $relation, Droit $attendu): void
    {
        self::assertSame($attendu, MatriceDroits::droit($ressource, $relation));
    }

    public function testToutesLesRessourcesSontCouvertes(): void
    {
        // Chaque ressource de l'application doit avoir ses cellules testées
        $testees = array_unique(array_map(
            static fn (array $cellule) => $cellule[0]->value,
            iterator_to_array(self::cellules(), false),
        ));

        self::assertEqualsCanonicalizing(array_map(static fn (Ressource $r) => $r->value, Ressource::cases()), array_values($testees));
    }

    public function testPieceVisibleDuCompteLieSeulementSiPartagee(): void
    {
        self::assertSame(Droit::Rien, MatriceDroits::droit(Ressource::Piece, Relation::CompteLie, partage: false));
        self::assertSame(Droit::Voir, MatriceDroits::droit(Ressource::Piece, Relation::CompteLie, partage: true));
        // Le partage ne change rien pour les autres relations
        self::assertSame(Droit::Rien, MatriceDroits::droit(Ressource::Piece, Relation::Abonne, partage: true));
    }

    public function testLookVisibleDesInconnusSeulementSiComptePublic(): void
    {
        foreach ([Ressource::Look, Ressource::ItemLook] as $ressource) {
            self::assertSame(Droit::Rien, MatriceDroits::droit($ressource, Relation::Inconnu, comptePublic: false));
            self::assertSame(Droit::Voir, MatriceDroits::droit($ressource, Relation::Inconnu, comptePublic: true));
        }
    }

    public function testUnMembreBloqueNAccedeARien(): void
    {
        foreach (Ressource::cases() as $ressource) {
            self::assertSame(Droit::Rien, MatriceDroits::droit($ressource, Relation::Bloque, partage: true, comptePublic: true));
        }
    }

    public function testComparaisonDesDroits(): void
    {
        self::assertTrue(Droit::Modifier->permet(Droit::Voir));
        self::assertFalse(Droit::Voir->permet(Droit::Modifier));
        self::assertSame(Droit::Voir, Droit::Modifier->plafonner(Droit::Voir));
        self::assertSame(Droit::Rien, Droit::Rien->plafonner(Droit::Voir));
    }
}
