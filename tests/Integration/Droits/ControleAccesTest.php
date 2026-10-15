<?php

namespace App\Tests\Integration\Droits;

use App\DataFixtures\UtilisateurFixtures;
use App\Droits\Acteur;
use App\Droits\ControleAcces;
use App\Droits\Droit;
use App\Droits\Relation;
use App\Entity\Categorie;
use App\Entity\Contenant;
use App\Entity\Logement;
use App\Entity\Look;
use App\Entity\Objet;
use App\Entity\Piece;
use App\Entity\SousCompte;
use App\Enum\NiveauSousCompte;
use App\Tests\Integration\IntegrationTestCase;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * La matrice des droits appliquée aux vraies données des fixtures : chaque ressource, chaque relation.
 * C'est ici que se vérifie la règle « qui a le droit de voir quoi » de bout en bout côté serveur.
 */
class ControleAccesTest extends IntegrationTestCase
{
    private ControleAcces $controle;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chargerFixtures();
        $this->controle = static::getContainer()->get(ControleAcces::class);
    }

    /**
     * Ressources de Camille, droit attendu pour : Camille, Léo (+18), Emma (enfant), Alex (lié), Sophie (amie),
     * Marc (abonné), Nina (inconnue), Bruno (bloqué).
     *
     * @return iterable<string, array{string, list<Droit>}>
     */
    public static function ressources(): iterable
    {
        $m = Droit::Modifier;
        $v = Droit::Voir;
        $r = Droit::Rien;

        yield 'logement (au moins une pièce partagée)' => ['logement', [$m, $m, $v, $v, $v, $r, $r, $r]];
        yield 'pièce non partagée (Chambre)' => ['piece:Chambre', [$m, $m, $v, $r, $v, $r, $r, $r]];
        yield 'pièce partagée (Dressing)' => ['piece:Dressing', [$m, $m, $v, $v, $v, $r, $r, $r]];
        yield 'rangement privé (Armoire)' => ['contenant:Armoire', [$m, $m, $m, $m, $r, $r, $r, $r]];
        yield 'rangement partagé (Commode)' => ['contenant:Commode', [$m, $m, $v, $m, $v, $v, $r, $r]];
        yield 'objet' => ['objet:Manteau bordeaux', [$m, $m, $v, $m, $v, $r, $r, $r]];
        yield 'objet personnel' => ['objet:Journal intime', [$m, $r, $r, $r, $r, $r, $r, $r]];
        yield 'catégorie personnelle (Sport)' => ['categorie:Sport', [$m, $m, $v, $m, $r, $r, $r, $r]];
        yield 'catégorie prédéfinie (Hauts)' => ['categorie:Hauts', [$v, $v, $v, $v, $v, $v, $v, $v]];
        yield 'look (compte privé)' => ['look', [$m, $v, $v, $v, $v, $v, $r, $r]];
    }

    /**
     * @param list<Droit> $attendus
     */
    #[DataProvider('ressources')]
    public function testChaqueRelationObtientLeDroitDeLaMatrice(string $ressource, array $attendus): void
    {
        $sujet = $this->ressource($ressource);

        foreach ($this->acteurs() as $rang => [$nom, $acteur]) {
            self::assertSame($attendus[$rang], $this->controle->droitSur($acteur, $sujet), sprintf('%s sur %s', $nom, $ressource));
        }
    }

    public function testLaRelationEstCalculeeDepuisLesLiensEnregistres(): void
    {
        $camille = $this->compte(UtilisateurFixtures::CAMILLE);
        $attendues = [Relation::Proprietaire, Relation::SousCompteMajeur, Relation::SousCompteEnfant, Relation::CompteLie, Relation::Ami, Relation::Abonne, Relation::Inconnu, Relation::Bloque];

        foreach ($this->acteurs() as $rang => [$nom, $acteur]) {
            self::assertSame($attendues[$rang], $this->controle->relation($acteur, $camille), $nom);
        }
    }

    public function testLaVueLimiteeEstEnLectureSeule(): void
    {
        $emma = $this->sousCompte('Emma')->setNiveau(NiveauSousCompte::VueLimitee);
        $acteur = new Acteur($this->compte(UtilisateurFixtures::CAMILLE), $emma);

        // Un enfant « simplifié » modifie les rangements ; en vue limitée, il ne fait plus que les voir
        self::assertSame(Droit::Voir, $this->controle->droitSur($acteur, $this->ressource('contenant:Armoire')));
    }

    public function testUnSujetInconnuNeDonneAucunDroit(): void
    {
        self::assertSame(Droit::Rien, $this->controle->droitSur(new Acteur($this->compte(UtilisateurFixtures::CAMILLE)), new \stdClass()));
    }

    /**
     * @return list<array{string, Acteur}>
     */
    private function acteurs(): array
    {
        $camille = $this->compte(UtilisateurFixtures::CAMILLE);

        return [
            ['Camille', new Acteur($camille)],
            ['Léo', new Acteur($camille, $this->sousCompte('Léo'))],
            ['Emma', new Acteur($camille, $this->sousCompte('Emma'))],
            ['Alex', new Acteur($this->compte(UtilisateurFixtures::ALEX))],
            ['Sophie', new Acteur($this->compte(UtilisateurFixtures::SOPHIE))],
            ['Marc', new Acteur($this->compte(UtilisateurFixtures::MARC))],
            ['Nina', new Acteur($this->compte(UtilisateurFixtures::NINA))],
            ['Bruno', new Acteur($this->compte(UtilisateurFixtures::BRUNO))],
        ];
    }

    private function ressource(string $cle): object
    {
        [$type, $nom] = array_pad(explode(':', $cle, 2), 2, null);
        $em = $this->em();

        $sujet = match ($type) {
            'logement' => $em->getRepository(Logement::class)->findOneBy([]),
            'piece' => $em->getRepository(Piece::class)->findOneBy(['nom' => $nom]),
            'contenant' => $em->getRepository(Contenant::class)->findOneBy(['nom' => $nom]),
            'objet' => $em->getRepository(Objet::class)->findOneBy(['nom' => $nom]),
            'categorie' => $em->getRepository(Categorie::class)->findOneBy(['nom' => $nom]),
            'look' => $em->getRepository(Look::class)->findOneBy([]),
            default => null,
        };
        self::assertNotNull($sujet, $cle);

        return $sujet;
    }

    private function sousCompte(string $prenom): SousCompte
    {
        $sousCompte = $this->em()->getRepository(SousCompte::class)->findOneBy(['prenom' => $prenom]);
        self::assertNotNull($sousCompte, $prenom);

        return $sousCompte;
    }
}
