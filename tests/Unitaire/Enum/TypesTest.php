<?php

namespace App\Tests\Unitaire\Enum;

use App\Enum\FormatPublication;
use App\Enum\NiveauSousCompte;
use App\Enum\TypeContenant;
use App\Enum\TypeLogement;
use App\Enum\TypePiece;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Chaque type métier a un libellé et une icône qui existe vraiment dans assets/icons/ph :
 * une icône manquante ferait planter la page qui l'affiche.
 */
class TypesTest extends TestCase
{
    /**
     * @return iterable<string, array{TypeLogement|TypeContenant|TypePiece|NiveauSousCompte|FormatPublication}>
     */
    public static function types(): iterable
    {
        foreach ([TypeLogement::cases(), TypeContenant::cases(), TypePiece::cases(), NiveauSousCompte::cases(), FormatPublication::cases()] as $cas) {
            foreach ($cas as $type) {
                yield $type::class.'::'.$type->name => [$type];
            }
        }
    }

    #[DataProvider('types')]
    public function testLibelleEtIconeExistante(TypeLogement|TypeContenant|TypePiece|NiveauSousCompte|FormatPublication $type): void
    {
        self::assertNotSame('', $type->libelle());
        self::assertStringStartsWith('ph:', $type->icone());
        self::assertFileExists(__DIR__.'/../../../assets/icons/ph/'.substr($type->icone(), 3).'.svg');
    }

    public function testChaqueNiveauDeSousCompteEstExplique(): void
    {
        foreach (NiveauSousCompte::cases() as $niveau) {
            self::assertNotSame('', $niveau->description(), $niveau->name);
        }
    }

    public function testLesPiecesProposeesExcluentLaPiecePersonnalisee(): void
    {
        $proposees = TypePiece::predefinies();

        self::assertNotContains(TypePiece::Personnalisee, $proposees);
        self::assertCount(\count(TypePiece::cases()) - 1, $proposees);
        self::assertSame(TypePiece::Chambre, $proposees[0], 'L\'ordre de déclaration est conservé');
    }
}
