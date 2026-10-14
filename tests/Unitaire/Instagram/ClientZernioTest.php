<?php

namespace App\Tests\Unitaire\Instagram;

use App\Enum\FormatPublication;
use App\Instagram\ClientZernio;
use App\Instagram\InstagramException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Cas d'erreur du client Zernio : chaque refus devient un message affichable.
 */
class ClientZernioTest extends TestCase
{
    private const PHOTO = __DIR__.'/../../fixtures/look-portrait.jpg';
    private const PRESIGN = '{"uploadUrl": "https://stockage.test/envoi", "publicUrl": "https://media.test/photo.jpg"}';

    public function testSansCleNiProfilLaConnexionEstDesactivee(): void
    {
        self::assertFalse((new ClientZernio(new MockHttpClient(), new NullLogger(), '', 'profil'))->estConfigure());
        self::assertFalse((new ClientZernio(new MockHttpClient(), new NullLogger(), 'sk_cle', null))->estConfigure());
    }

    public function testUneAdresseDeConnexionNonHttpsEstRefusee(): void
    {
        $this->attendreErreur('pas renvoyé d\'adresse de connexion');

        $this->client([new MockResponse('{"authUrl": "http://zernio.test/oauth"}')])->urlConnexion('https://dressing.test/retour');
    }

    public function testUnRefusDeZernioRemonteSonMessage(): void
    {
        $this->attendreErreur('Zernio a refusé la demande (Profile not found)');

        $this->client([new MockResponse('{"error": "Profile not found"}', ['http_code' => 404])])->urlConnexion('https://dressing.test/retour');
    }

    public function testUnRefusSansMessageAfficheLeStatut(): void
    {
        $this->attendreErreur('Zernio a refusé la demande (500)');

        $this->client([new MockResponse('', ['http_code' => 500])])->compteInstagram('compte-1');
    }

    public function testZernioInjoignable(): void
    {
        $this->attendreErreur('Zernio ne répond pas');

        $this->client([new MockResponse('', ['error' => 'délai dépassé'])])->compteInstagram('compte-1');
    }

    public function testUnCompteAbsentDuProfil(): void
    {
        $client = $this->client([new MockResponse('{"accounts": "liste inattendue"}')]);

        self::assertNull($client->compteInstagram('compte-1'));
    }

    public function testUnCompteDejaRetireNEstPasUneErreur(): void
    {
        $this->client([new MockResponse('{"error": "Not found"}', ['http_code' => 404])])->deconnecter('compte-1');

        $this->addToAssertionCount(1);
    }

    public function testUnePhotoIntrouvableNEstPasEnvoyee(): void
    {
        $this->attendreErreur('photo du look est introuvable');

        $this->client([])->publier('compte-1', '/introuvable.jpg', FormatPublication::Feed, 'Légende');
    }

    public function testSansAdresseDEnvoiLaPublicationEchoue(): void
    {
        $this->attendreErreur('pas fourni d\'adresse d\'envoi');

        $this->client([new MockResponse('{"uploadUrl": "https://stockage.test/envoi"}')])->publier('compte-1', self::PHOTO, FormatPublication::Feed, 'Légende');
    }

    public function testUnEnvoiDePhotoRefuseParLeStockage(): void
    {
        $this->attendreErreur('L\'envoi de la photo a échoué');

        $this->client([new MockResponse(self::PRESIGN), new MockResponse('', ['http_code' => 403])])->publier('compte-1', self::PHOTO, FormatPublication::Feed, 'Légende');
    }

    public function testUnStockageInjoignable(): void
    {
        $this->attendreErreur('L\'envoi de la photo a échoué');

        $this->client([new MockResponse(self::PRESIGN), new MockResponse('', ['error' => 'connexion coupée'])])->publier('compte-1', self::PHOTO, FormatPublication::Feed, 'Légende');
    }

    public function testUnLienDePublicationNonHttpsNEstPasConserve(): void
    {
        $client = $this->client([
            new MockResponse(self::PRESIGN),
            new MockResponse(''),
            new MockResponse('{"post": {"_id": "post-1", "status": "published", "platforms": [{"status": "published", "platformPostUrl": "javascript:alert(1)"}]}}'),
        ]);

        $resultat = $client->publier('compte-1', self::PHOTO, FormatPublication::Feed, 'Légende');

        self::assertSame('post-1', $resultat->zernioId);
        self::assertNull($resultat->url);
    }

    private function attendreErreur(string $message): void
    {
        $this->expectException(InstagramException::class);
        $this->expectExceptionMessage($message);
    }

    /**
     * @param list<MockResponse> $reponses
     */
    private function client(array $reponses): ClientZernio
    {
        return new ClientZernio(new MockHttpClient($reponses), new NullLogger(), 'sk_cle', 'profil');
    }
}
