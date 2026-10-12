<?php

namespace App\Tests\Controller;

use App\Entity\CompteInstagram;
use App\Entity\Lien;
use App\Entity\Look;
use App\Entity\PublicationInstagram;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Enum\FormatPublication;
use App\Enum\TypeLien;
use App\Instagram\ClientZernio;
use App\Look\StockagePhotos;
use App\Tests\Instagram\ReponsesZernio;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Looks : création avec photo, droits de lecture et publication sur Instagram via Zernio.
 */
class LookTest extends AppWebTestCase
{
    private const FIXTURES = __DIR__.'/../fixtures/';

    private User $camille;

    protected function setUp(): void
    {
        parent::setUp();
        ReponsesZernio::$requetes = [];
        $this->camille = $this->creerUtilisateur('camille@example.com', 'Camille');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove(static::getContainer()->getParameter('kernel.project_dir').'/var/uploads/test');
        parent::tearDown();
    }

    public function testCreationDUnLookAvecPhoto(): void
    {
        $this->client->loginUser($this->camille);
        $this->client->request('GET', '/looks');
        self::assertSelectorTextContains('.etat-vide__titre', 'Aucun look');

        $crawler = $this->client->request('GET', '/looks/nouveau');
        $form = $crawler->selectButton('Enregistrer le look')->form();
        $form['look[photo]']->upload($this->copie('look-portrait.jpg'));
        $form['look[titre]'] = 'Manteau bordeaux';
        $form['look[description]'] = 'Manteau Sandro, taille M';
        $this->client->submit($form);

        $look = $this->em()->getRepository(Look::class)->findOneBy(['titre' => 'Manteau bordeaux']);
        self::assertNotNull($look);
        self::assertResponseRedirects('/looks/'.$look->getId());
        self::assertSame('Manteau Sandro, taille M', $look->getDescription());
        self::assertFileExists($this->stockage()->chemin((string) $look->getPhoto()));

        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Manteau bordeaux');

        $this->client->request('GET', '/looks/'.$look->getId().'/photo');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/jpeg');

        $this->client->request('GET', '/looks');
        self::assertSelectorTextContains('.vignette-look__titre', 'Manteau bordeaux');
    }

    public function testLaPhotoEstObligatoireEtDoitEtreUneImage(): void
    {
        $this->client->loginUser($this->camille);

        $crawler = $this->client->request('GET', '/looks/nouveau');
        $this->client->submit($crawler->selectButton('Enregistrer le look')->form(['look[titre]' => 'Sans photo']));
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs', 'Ajoute une photo de ton look.');

        $form = $crawler->selectButton('Enregistrer le look')->form(['look[titre]' => 'Faux fichier']);
        $form['look[photo]']->upload($this->copie('pas-une-image.jpg'));
        $this->client->submit($form);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.erreurs', 'La photo doit être au format JPEG ou PNG.');

        self::assertSame(0, $this->em()->getRepository(Look::class)->count([]));
    }

    public function testUnInconnuNeVoitLesLooksQueSiLeCompteEstPublic(): void
    {
        $look = $this->creerLook();
        $this->client->loginUser($this->creerUtilisateur('inconnu@example.com', 'Inconnu'));

        $this->client->request('GET', '/looks/'.$look->getId());
        self::assertResponseStatusCodeSame(403);
        $this->client->request('GET', '/looks/'.$look->getId().'/photo');
        self::assertResponseStatusCodeSame(403);

        $this->em()->find(User::class, $this->camille->getId())->setComptePublic(true);
        $this->em()->flush();

        $this->client->request('GET', '/looks/'.$look->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.bouton--instagram');
    }

    public function testUneAmieVoitLesLooksSansPouvoirLesPublier(): void
    {
        $look = $this->creerLook();
        $amie = $this->creerUtilisateur('sophie@example.com', 'Sophie');
        $this->em()->persist((new Lien(TypeLien::Ami, $amie, $this->camille))->accepter());
        $this->em()->flush();
        $this->client->loginUser($amie);

        $this->client->request('GET', '/membres/'.$this->camille->getId());
        self::assertSelectorTextContains('.vignette-look__titre', 'Tenue du dimanche');

        $this->client->request('GET', '/looks/'.$look->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('.sous-titre', 'Look de Camille');
        self::assertSelectorNotExists('.bouton--instagram');
        self::assertSelectorNotExists('.invite-instagram');
        self::assertSelectorNotExists('.fiche-look__suppression');

        $this->client->request('POST', '/looks/'.$look->getId().'/instagram');
        self::assertResponseStatusCodeSame(403);
    }

    public function testUnSousCompteVoitLesLooksSansPouvoirEnCreer(): void
    {
        $look = $this->creerLook();
        $sousCompte = (new SousCompte())->setPrenom('Emma')->setDateNaissance(new \DateTimeImmutable('-20 years'));
        $this->camille->addSousCompte($sousCompte);
        $this->em()->persist($sousCompte);
        $this->em()->flush();

        $this->client->loginUser($this->camille);
        $crawler = $this->client->request('GET', '/profil');
        $this->client->submit($crawler->filter('form[action="/profils/'.$sousCompte->getId().'/activer"]')->form());

        $this->client->request('GET', '/looks');
        self::assertSelectorTextContains('h1', 'Looks de Camille');
        self::assertSelectorTextContains('.vignette-look__titre', 'Tenue du dimanche');
        self::assertSelectorNotExists('a[href="/looks/nouveau"]');

        $this->client->request('GET', '/looks/'.$look->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('.bouton--instagram');

        $this->client->request('GET', '/looks/nouveau');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSansCompteInstagramOnInviteAConnecter(): void
    {
        $look = $this->creerLook();
        $this->client->loginUser($this->camille);

        $this->client->request('GET', '/looks/'.$look->getId());
        self::assertSelectorExists('.invite-instagram[href="/profil/parametres#instagram"]');
        self::assertSelectorNotExists('.feuille');

        $this->client->request('POST', '/looks/'.$look->getId().'/instagram');
        self::assertResponseRedirects('/profil/parametres#instagram');
        self::assertSame([], ReponsesZernio::$requetes);
    }

    public function testPublicationDansLeFeed(): void
    {
        $look = $this->creerLook();
        $this->connecterInstagram();

        $crawler = $this->client->request('GET', '/looks/'.$look->getId());
        $form = $crawler->filter('.feuille form')->form();
        self::assertSame('feed', $form['publication_instagram[format]']->getValue());
        self::assertSame('Manteau bordeaux et jean brut', $form['publication_instagram[legende]']->getValue());

        $this->client->submit($form, ['publication_instagram[legende]' => 'Nouveau look ✨ via Dressing']);
        self::assertResponseRedirects('/looks/'.$look->getId());

        // 1. Photo envoyée dans le stockage Zernio, sans la clé d'API
        $presign = ReponsesZernio::derniere('POST', ClientZernio::URL_API.'/media/presign');
        self::assertSame('image/jpeg', ReponsesZernio::corps($presign)['contentType']);
        $envoi = ReponsesZernio::derniere('PUT', ReponsesZernio::URL_ENVOI);
        self::assertSame(['Content-Type: image/jpeg'], $envoi['options']['normalized_headers']['content-type']);
        self::assertArrayNotHasKey('authorization', $envoi['options']['normalized_headers']);
        self::assertSame(file_get_contents(self::FIXTURES.'look-portrait.jpg'), $envoi['options']['body']);

        // 2. Publication immédiate dans le feed du compte connecté
        $publication = ReponsesZernio::corps(ReponsesZernio::derniere('POST', ClientZernio::URL_API.'/posts'));
        self::assertSame([
            'content' => 'Nouveau look ✨ via Dressing',
            'mediaItems' => [['type' => 'image', 'url' => ReponsesZernio::URL_MEDIA]],
            'platforms' => [['platform' => 'instagram', 'accountId' => ReponsesZernio::COMPTE_ID]],
            'publishNow' => true,
        ], $publication);

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Ton look est publié sur Instagram.');
        self::assertSelectorExists('a[href="'.ReponsesZernio::URL_PUBLICATION.'"]');

        $enregistree = $this->em()->getRepository(PublicationInstagram::class)->findOneBy([]);
        self::assertSame(FormatPublication::Feed, $enregistree->getFormat());
        self::assertSame('post-1', $enregistree->getZernioId());

        $this->client->request('GET', '/looks');
        self::assertSelectorExists('.vignette-look__instagram');
    }

    public function testPublicationEnStory(): void
    {
        $look = $this->creerLook();
        $this->connecterInstagram();

        $crawler = $this->client->request('GET', '/looks/'.$look->getId());
        $this->client->submit($crawler->filter('.feuille form')->form(['publication_instagram[format]' => 'story']));
        self::assertResponseRedirects('/looks/'.$look->getId());

        $publication = ReponsesZernio::corps(ReponsesZernio::derniere('POST', ClientZernio::URL_API.'/posts'));
        self::assertSame(['contentType' => 'story'], $publication['platforms'][0]['platformSpecificData']);

        $this->client->followRedirect();
        self::assertSelectorTextContains('.flash', 'Ta story est en ligne sur Instagram.');
    }

    public function testUnePhotoTropEtireeNePartPasDansLeFeed(): void
    {
        $look = $this->creerLook('look-panorama.png');
        $this->connecterInstagram();

        $crawler = $this->client->request('GET', '/looks/'.$look->getId());
        $this->client->submit($crawler->filter('.feuille form')->form());

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.feuille .erreurs', 'trop étirée pour le feed');
        // La feuille se rouvre d'elle-même pour afficher l'erreur
        self::assertSelectorExists('[data-feuille-ouvert-value="true"]');
        self::assertNull(ReponsesZernio::derniere('POST', ClientZernio::URL_API.'/posts'));
    }

    public function testLeRefusDInstagramEstAffiche(): void
    {
        $look = $this->creerLook();
        $this->connecterInstagram();

        $crawler = $this->client->request('GET', '/looks/'.$look->getId());
        $this->client->submit($crawler->filter('.feuille form')->form(['publication_instagram[legende]' => ReponsesZernio::LEGENDE_REFUSEE]));

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('.feuille .erreurs', 'Instagram a refusé la publication (Media download has failed).');
        self::assertSame(0, $this->em()->getRepository(PublicationInstagram::class)->count([]));
    }

    public function testSuppressionDUnLook(): void
    {
        $look = $this->creerLook();
        $photo = $this->stockage()->chemin((string) $look->getPhoto());
        $this->client->loginUser($this->camille);

        $crawler = $this->client->request('GET', '/looks/'.$look->getId());
        $this->client->submit($crawler->filter('.fiche-look__suppression')->form());

        self::assertResponseRedirects('/looks');
        self::assertFileDoesNotExist($photo);
        self::assertSame(0, $this->em()->getRepository(Look::class)->count([]));
    }

    /**
     * Look de Camille dont la photo est déjà rangée dans le stockage.
     */
    private function creerLook(string $fixture = 'look-portrait.jpg'): Look
    {
        $nom = 'test-'.bin2hex(random_bytes(4)).'.'.pathinfo($fixture, \PATHINFO_EXTENSION);
        (new Filesystem())->copy(self::FIXTURES.$fixture, $this->stockage()->chemin($nom));

        $look = (new Look($this->camille))
            ->setTitre('Tenue du dimanche')
            ->setDescription('Manteau bordeaux et jean brut')
            ->setPhoto($nom);
        $this->em()->persist($look);
        $this->em()->flush();

        return $look;
    }

    private function connecterInstagram(): void
    {
        $this->em()->persist(new CompteInstagram($this->camille, ReponsesZernio::COMPTE_ID, ReponsesZernio::PSEUDO));
        $this->em()->flush();
        $this->client->loginUser($this->camille);
    }

    /**
     * Copie temporaire : le formulaire déplace le fichier envoyé.
     */
    private function copie(string $fixture): string
    {
        $copie = sys_get_temp_dir().'/'.uniqid('look-', true).'-'.$fixture;
        copy(self::FIXTURES.$fixture, $copie);

        return $copie;
    }

    private function stockage(): StockagePhotos
    {
        return static::getContainer()->get(StockagePhotos::class);
    }
}
