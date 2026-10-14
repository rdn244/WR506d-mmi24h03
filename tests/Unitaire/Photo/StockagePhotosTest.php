<?php

namespace App\Tests\Unitaire\Photo;

use App\Photo\PhotoBase64;
use App\Photo\StockagePhotos;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Stockage des photos hors de public/ : nommage aléatoire, service, suppression.
 */
class StockagePhotosTest extends TestCase
{
    private const PHOTO = __DIR__.'/../../fixtures/look-portrait.jpg';

    private string $dossier;
    private StockagePhotos $stockage;

    protected function setUp(): void
    {
        $this->dossier = sys_get_temp_dir().'/stockage-'.bin2hex(random_bytes(4));
        $this->stockage = new StockagePhotos($this->dossier);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->dossier);
    }

    public function testUnFichierEnvoyeEstRangeSousUnNomAleatoire(): void
    {
        $copie = sys_get_temp_dir().'/envoi-'.bin2hex(random_bytes(4)).'.jpg';
        copy(self::PHOTO, $copie);

        $nom = $this->stockage->enregistrer(new UploadedFile($copie, 'mon manteau.jpg', 'image/jpeg', null, true));

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}\.jpg$/', $nom);
        self::assertTrue($this->stockage->existe($nom));
        self::assertFileEquals(self::PHOTO, $this->stockage->chemin($nom));
    }

    public function testUnePhotoDecodeeEstEnregistreeEtServie(): void
    {
        $photo = PhotoBase64::depuisDataUrl('data:image/jpeg;base64,'.base64_encode((string) file_get_contents(self::PHOTO)));

        $nom = $this->stockage->enregistrerContenu($photo);
        $reponse = $this->stockage->reponse($nom);

        self::assertSame($this->stockage->chemin($nom), $reponse->getFile()->getPathname());
        self::assertTrue($reponse->headers->hasCacheControlDirective('private'));
        self::assertSame('nosniff', $reponse->headers->get('X-Content-Type-Options'));
    }

    public function testLeCheminNeSortJamaisDuDossier(): void
    {
        self::assertSame($this->dossier.'/passwd', $this->stockage->chemin('../../etc/passwd'));
    }

    public function testUnePhotoAbsenteRepond404(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->stockage->reponse('absente.jpg');
    }

    public function testSuppression(): void
    {
        $nom = $this->stockage->enregistrerContenu(PhotoBase64::depuisDataUrl('data:image/jpeg;base64,'.base64_encode((string) file_get_contents(self::PHOTO))));

        $this->stockage->supprimer($nom);

        self::assertFalse($this->stockage->existe($nom));
    }
}
