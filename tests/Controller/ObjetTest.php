<?php

namespace App\Tests\Controller;

use App\Entity\Categorie;
use App\Entity\Contenant;
use App\Entity\Lien;
use App\Entity\Objet;
use App\Entity\Piece;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Enum\NiveauSousCompte;
use App\Enum\TypeContenant;
use App\Enum\TypeLien;
use App\Enum\TypePiece;
use App\Photo\StockagePhotos;
use App\Scan\AnalyseurObjet;
use App\Tests\Scan\ReponsesOpenRouter;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Scan IA d'un objet (OpenRouter simulé) et inventaire.
 */
class ObjetTest extends AppWebTestCase
{
    private const PHOTO = __DIR__.'/../fixtures/look-portrait.jpg';

    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();
        ReponsesOpenRouter::reinitialiser();
        $this->camille = $this->creerUtilisateur('camille@example.com', 'Camille');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(static::getContainer()->getParameter('kernel.project_dir').'/var/uploads/test');
        parent::tearDown();
    }

    public function testLaPhotoPartEnBase64AuPremierModeleGratuit(): void
    {
        $this->client->loginUser($this->camille);

        $reponse = $this->analyser();

        self::assertResponseIsSuccessful();
        // La catégorie choisie par le modèle est rapprochée de la liste du compte (sans tenir compte de la casse)
        self::assertSame([
            'champs' => [
                'nom' => 'Manteau bordeaux',
                'marque' => 'Sandro Paris',
                'type' => 'Manteau',
                'matiere' => 'Laine mélangée',
                'couleur' => 'Bordeaux',
                'categorie' => $this->categorie('Vestes et manteaux')->getId(),
            ],
            'modele' => AnalyseurObjet::MODELES_PAR_DEFAUT[0],
        ], $reponse);

        $requete = ReponsesOpenRouter::$requetes[0];
        self::assertContains('Authorization: Bearer sk-or-test', $requete['options']['normalized_headers']['authorization']);
        // Réflexion coupée : réponse rapide et jamais vide
        self::assertSame(['enabled' => false], ReponsesOpenRouter::corps($requete)['reasoning']);
        // Le modèle choisit parmi les catégories prédéfinies
        self::assertStringContainsString('Hauts, Bas, Robes, Vestes et manteaux', ReponsesOpenRouter::corps($requete)['messages'][1]['content'][0]['text']);
        $image = ReponsesOpenRouter::corps($requete)['messages'][1]['content'][1];
        self::assertSame('image_url', $image['type']);
        self::assertSame($this->dataUrl(), $image['image_url']['url']);
        // Modèles gratuits uniquement
        foreach (AnalyseurObjet::MODELES_PAR_DEFAUT as $modele) {
            self::assertStringEndsWith(':free', $modele);
        }
    }

    public function testLaChaineDeSecoursPasseAuModeleSuivant(): void
    {
        [$premier, $deuxieme, $troisieme, $quatrieme] = AnalyseurObjet::MODELES_PAR_DEFAUT;
        ReponsesOpenRouter::$scenarios = [
            $premier => ReponsesOpenRouter::QUOTA,
            $deuxieme => ReponsesOpenRouter::ERREUR_DANS_200,
            $troisieme => ReponsesOpenRouter::TEXTE_LIBRE,
            $quatrieme => ReponsesOpenRouter::JSON_ENTOURE,
        ];
        $this->client->loginUser($this->camille);

        $reponse = $this->analyser();

        self::assertResponseIsSuccessful();
        self::assertSame($quatrieme, $reponse['modele']);
        self::assertSame('Sandro Paris', $reponse['champs']['marque']);
        self::assertSame([$premier, $deuxieme, $troisieme, $quatrieme], ReponsesOpenRouter::modelesInterroges());
    }

    public function testLesClesAccentueesOuEnAnglaisSontComprises(): void
    {
        ReponsesOpenRouter::$scenarios = [AnalyseurObjet::MODELES_PAR_DEFAUT[0] => ReponsesOpenRouter::CLES_ACCENTUEES];
        $this->client->loginUser($this->camille);

        $reponse = $this->analyser();

        self::assertSame([
            'nom' => 'Jean brut',
            'marque' => 'Levi\'s',
            'type' => 'Jean',
            'matiere' => 'Denim',
            'couleur' => 'Bleu',
            'categorie' => $this->categorie('Bas')->getId(),
        ], $reponse['champs']);
    }

    public function testQuandTousLesModelesEchouentOnRemplitALaMain(): void
    {
        [$premier, $deuxieme, $troisieme, $quatrieme] = AnalyseurObjet::MODELES_PAR_DEFAUT;
        ReponsesOpenRouter::$scenarios = [
            $premier => ReponsesOpenRouter::QUOTA,
            $deuxieme => ReponsesOpenRouter::RESERVE,
            $troisieme => ReponsesOpenRouter::QUOTA,
            $quatrieme => ReponsesOpenRouter::ERREUR_DANS_200,
        ];
        $this->client->loginUser($this->camille);

        $reponse = $this->analyser();

        self::assertResponseStatusCodeSame(502);
        self::assertStringContainsString('Remplis les champs à la main', $reponse['message']);
        // Second tour : seuls les modèles saturés sont réessayés, pas celui qui refuse (403)
        self::assertSame([$premier, $deuxieme, $troisieme, $quatrieme, $premier, $troisieme, $quatrieme], ReponsesOpenRouter::modelesInterroges());
    }

    public function testUnModeleSatureEstReessayeAuSecondTour(): void
    {
        ReponsesOpenRouter::$scenarios = array_fill_keys(AnalyseurObjet::MODELES_PAR_DEFAUT, ReponsesOpenRouter::QUOTA);
        $this->client->loginUser($this->camille);
        $requetes = 0;
        // Le quota du premier modèle se libère après le premier tour
        ReponsesOpenRouter::$avantReponse = static function () use (&$requetes): void {
            if (++$requetes === \count(AnalyseurObjet::MODELES_PAR_DEFAUT)) {
                unset(ReponsesOpenRouter::$scenarios[AnalyseurObjet::MODELES_PAR_DEFAUT[0]]);
            }
        };

        $reponse = $this->analyser();

        self::assertResponseIsSuccessful();
        self::assertSame(AnalyseurObjet::MODELES_PAR_DEFAUT[0], $reponse['modele']);
        self::assertCount(\count(AnalyseurObjet::MODELES_PAR_DEFAUT) + 1, ReponsesOpenRouter::$requetes);
    }

    public function testUneCleRefuseeArreteLaChaine(): void
    {
        ReponsesOpenRouter::$scenarios = [AnalyseurObjet::MODELES_PAR_DEFAUT[0] => ReponsesOpenRouter::CLE_REFUSEE];
        $this->client->loginUser($this->camille);

        $reponse = $this->analyser();

        self::assertResponseStatusCodeSame(502);
        self::assertStringContainsString('clé OpenRouter est refusée', $reponse['message']);
        self::assertCount(1, ReponsesOpenRouter::$requetes);
    }

    public function testUnFichierQuiNEstPasUneImageNEstPasEnvoye(): void
    {
        $this->client->loginUser($this->camille);

        $reponse = $this->analyser('data:image/jpeg;base64,'.base64_encode('pas une image'));

        self::assertResponseStatusCodeSame(422);
        self::assertSame('La photo est illisible.', $reponse['message']);
        self::assertSame([], ReponsesOpenRouter::$requetes);
    }

    public function testLesAnalysesSontLimiteesParMinute(): void
    {
        $this->client->loginUser($this->camille);

        for ($i = 0; $i < 10; ++$i) {
            $this->analyser();
            self::assertResponseIsSuccessful();
        }

        $reponse = $this->analyser();
        self::assertResponseStatusCodeSame(429);
        self::assertStringContainsString('patiente une minute', $reponse['message']);
    }

    public function testValiderLeScanAjouteLObjetAuDressing(): void
    {
        $armoire = $this->creerArmoire($this->camille);
        $this->client->loginUser($this->camille);

        $crawler = $this->client->request('GET', '/objets/nouveau');
        self::assertSelectorExists('[data-controller="scan"][data-scan-disponible-value="true"]');
        $this->client->submit($crawler->selectButton('Valider et ajouter à mon dressing')->form(), [
            'objet[photo]' => $this->dataUrl(),
            'objet[nom]' => 'Manteau bordeaux',
            'objet[marque]' => 'Sandro Paris',
            'objet[type]' => 'Manteau',
            'objet[categorie]' => (string) $this->categorie('Vestes et manteaux')->getId(),
            'objet[matiere]' => 'Laine mélangée',
            'objet[couleur]' => 'Bordeaux',
            'objet[contenant]' => (string) $armoire->getId(),
            'objet[modeleIa]' => AnalyseurObjet::MODELES_PAR_DEFAUT[0],
        ]);

        $objet = $this->em()->getRepository(Objet::class)->findOneBy(['nom' => 'Manteau bordeaux']);
        self::assertNotNull($objet);
        self::assertResponseRedirects('/objets/'.$objet->getId());
        self::assertSame('Armoire', $objet->getContenant()?->getNom());
        self::assertSame('Vestes et manteaux', $objet->getCategorie()?->getNom());
        self::assertSame('Manteau', $objet->getType());
        self::assertSame(file_get_contents(self::PHOTO), file_get_contents($this->photosObjets()->chemin((string) $objet->getPhoto())));

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', '« Manteau bordeaux » est ajouté à ton dressing.');
        self::assertSelectorTextContains('.sous-titre', 'Sandro Paris · Chambre › Armoire');
        self::assertSelectorTextContains('.mention', AnalyseurObjet::MODELES_PAR_DEFAUT[0]);
        self::assertSelectorTextContains('.etiquette--categorie', 'Vestes et manteaux');

        $this->client->request('GET', '/objets/'.$objet->getId().'/photo');
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');

        $this->client->request('GET', '/objets');
        self::assertSelectorTextContains('.vignette__titre', 'Manteau bordeaux');
    }

    public function testUnePhotoFalsifieeEstRefuseeEtLeNomObligatoire(): void
    {
        $this->client->loginUser($this->camille);

        $crawler = $this->client->request('GET', '/objets/nouveau');
        $this->client->submit($crawler->selectButton('Valider et ajouter à mon dressing')->form(), [
            'objet[photo]' => 'data:image/png;base64,'.base64_encode('<?php echo 1;'),
            'objet[nom]' => '',
        ]);

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs--formulaire', 'La photo est illisible.');
        self::assertSelectorTextContains('.champ .erreurs', 'Donne un nom à l\'objet.');
        self::assertSame(0, $this->em()->getRepository(Objet::class)->count([]));
    }

    public function testOnNePeutPasRangerUnObjetChezQuelquUnDAutre(): void
    {
        $armoireDAlex = $this->creerArmoire($this->creerUtilisateur('alex@example.com', 'Alex'));
        $this->client->loginUser($this->camille);

        $crawler = $this->client->request('GET', '/objets/nouveau');
        $form = $crawler->selectButton('Valider et ajouter à mon dressing')->form(['objet[nom]' => 'Pull']);
        $contenant = $form['objet[contenant]'];
        self::assertInstanceOf(ChoiceFormField::class, $contenant);
        $contenant->disableValidation()->setValue((string) $armoireDAlex->getId());
        $this->client->submit($form);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->em()->getRepository(Objet::class)->count([]));
    }

    public function testUnObjetPersonnelNEstVuQueParSonProprietaire(): void
    {
        $prive = (new Objet($this->camille))->setNom('Journal intime')->setPersonnel(true);
        $public = (new Objet($this->camille))->setNom('Pull marine');
        $this->em()->persist($prive);
        $this->em()->persist($public);
        $amie = $this->creerUtilisateur('sophie@example.com', 'Sophie');
        $this->em()->persist((new Lien(TypeLien::Ami, $amie, $this->camille))->accepter());
        $this->em()->flush();

        $this->client->loginUser($amie);
        $this->client->request('GET', '/objets/'.$public->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.fiche__suppression');
        $this->client->request('GET', '/objets/'.$prive->getId());
        self::assertResponseStatusCodeSame(403);

        $this->client->loginUser($this->camille);
        $this->client->request('GET', '/objets/'.$prive->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.etiquette--prive', 'Privé');
    }

    public function testUnSousCompteEnfantNePeutNiScannerNiAnalyser(): void
    {
        $emma = (new SousCompte())->setPrenom('Emma')->setDateNaissance(new \DateTimeImmutable('-9 years'))->setNiveau(NiveauSousCompte::Simplifie);
        $this->camille->addSousCompte($emma);
        $this->em()->persist($emma);
        $this->em()->flush();

        $this->client->loginUser($this->camille);
        $jeton = $this->jetonScan();
        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('form[action="/profils/'.$emma->getId().'/activer"]')->form());

        $this->client->request('GET', '/objets');
        self::assertSelectorNotExists('a[href="/objets/nouveau"]');
        $this->client->request('GET', '/objets/nouveau');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/objets/analyser', ['image' => $this->dataUrl(), '_token' => $jeton]);
        self::assertResponseStatusCodeSame(403);
        self::assertSame([], ReponsesOpenRouter::$requetes);
    }

    public function testSuppressionDUnObjet(): void
    {
        $this->client->loginUser($this->camille);
        $crawler = $this->client->request('GET', '/objets/nouveau');
        $this->client->submit($crawler->selectButton('Valider et ajouter à mon dressing')->form(), [
            'objet[photo]' => $this->dataUrl(),
            'objet[nom]' => 'Vieux pull',
        ]);
        $objet = $this->em()->getRepository(Objet::class)->findOneBy(['nom' => 'Vieux pull']);
        $photo = $this->photosObjets()->chemin((string) $objet->getPhoto());
        self::assertFileExists($photo);

        $crawler = $this->client->request('GET', '/objets/'.$objet->getId());
        $this->client->submit($crawler->filter('.fiche__suppression')->form());

        self::assertResponseRedirects('/objets');
        self::assertFileDoesNotExist($photo);
        self::assertSame(0, $this->em()->getRepository(Objet::class)->count([]));
    }

    /**
     * Appelle l'analyse comme le fait la page de scan et renvoie le JSON reçu.
     *
     * @return array<string, mixed>
     */
    private function analyser(?string $image = null): array
    {
        $jeton = $this->jetonScan();
        $this->client->request('POST', '/objets/analyser', [
            'image' => $image ?? $this->dataUrl(),
            '_token' => $jeton,
        ]);

        return json_decode((string) $this->client->getResponse()->getContent(), true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * Jeton CSRF tel que la page de scan le transmet au contrôleur Stimulus.
     */
    private function jetonScan(): string
    {
        $crawler = $this->client->request('GET', '/objets/nouveau');

        return (string) $crawler->filter('[data-controller="scan"]')->attr('data-scan-jeton-value');
    }

    private function dataUrl(): string
    {
        return 'data:image/jpeg;base64,'.base64_encode((string) file_get_contents(self::PHOTO));
    }

    private function creerArmoire(User $proprietaire): Contenant
    {
        $logement = $this->creerLogement($proprietaire);
        $chambre = Piece::depuisType(TypePiece::Chambre);
        $logement->addPiece($chambre);
        $armoire = (new Contenant())->setNom('Armoire')->setType(TypeContenant::Armoire);
        $chambre->addContenant($armoire);
        $this->em()->flush();

        return $armoire;
    }

    private function categorie(string $nom): Categorie
    {
        return $this->em()->getRepository(Categorie::class)->findOneBy(['nom' => $nom, 'proprietaire' => null]);
    }

    private function photosObjets(): StockagePhotos
    {
        return static::getContainer()->get('app.photos.objets');
    }
}
