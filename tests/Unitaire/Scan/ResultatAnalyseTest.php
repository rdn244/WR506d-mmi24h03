<?php

namespace App\Tests\Unitaire\Scan;

use App\Scan\ResultatAnalyse;
use PHPUnit\Framework\TestCase;

/**
 * Nettoyage d'une réponse de modèle : jamais fiable, toujours bornée.
 */
class ResultatAnalyseTest extends TestCase
{
    public function testLesValeursSontNettoyeesEtTronquees(): void
    {
        $resultat = ResultatAnalyse::depuisReponse([
            'nom' => "  <b>manteau</b>\n   long ",
            'marque' => 'Inconnue',
            'type' => str_repeat('x', 200),
            'matiere' => ['laine'],
            'couleur' => '',
        ], 'modele:free');

        self::assertSame('Manteau long', $resultat->nom);
        self::assertNull($resultat->marque, '« Inconnue » veut dire « je ne sais pas »');
        self::assertSame(ResultatAnalyse::LONGUEURS['type'], mb_strlen((string) $resultat->type));
        self::assertNull($resultat->matiere, 'Une valeur qui n\'est pas du texte est ignorée');
        self::assertNull($resultat->couleur);
        self::assertSame('modele:free', $resultat->modele);
    }

    public function testUneReponseSansNomNiTypeNiCategorieEstVide(): void
    {
        self::assertTrue(ResultatAnalyse::depuisReponse(['couleur' => 'Rouge'], 'm')->estVide());
        self::assertFalse(ResultatAnalyse::depuisReponse(['type' => 'Jean'], 'm')->estVide());
    }

    public function testLaCategorieEstRapprocheeDeLaListeSansTenirCompteDeLaCasse(): void
    {
        $champs = ResultatAnalyse::depuisReponse(['nom' => 'Pull', 'categorie' => 'HAUTS'], 'm')->champs([3 => 'Bas', 7 => 'Hauts']);

        self::assertSame(7, $champs['categorie']);
        self::assertNull($champs['type']);
    }

    public function testUneCategorieHorsListeDevientLeType(): void
    {
        $champs = ResultatAnalyse::depuisReponse(['categorie' => 'Manteau'], 'm')->champs([1 => 'Hauts']);

        self::assertNull($champs['categorie']);
        self::assertSame('Manteau', $champs['type']);
        self::assertSame('Manteau', $champs['nom'], 'Sans nom, le type sert de nom');
    }
}
