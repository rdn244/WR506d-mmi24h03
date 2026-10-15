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
 * Chemins de réussite du client Zernio : connexion, compte retrouvé, publication en feed et en story.
 */
class ClientZernioPublicationTest extends TestCase
{
    private const PHOTO = __DIR__.'/../../fixtures/look-portrait.jpg';
    private const PRESIGN = '{"uploadUrl": "https://stockage.test/envoi", "publicUrl": "https://media.test/photo.jpg"}';

    public function testLAdresseDeConnexionEstDemandeePourLeProfil(): void
    {
        $requetes = [];
        $client = $this->client(static function (string $methode, string $url) use (&$requetes) {
            $requetes[] = $url;

            return new MockResponse('{"authUrl": "https://zernio.com/oauth/abc"}');
        });

        self::assertSame('https://zernio.com/oauth/abc', $client->urlConnexion('https://dressing.test/instagram/retour'));
        parse_str((string) parse_url($requetes[0], \PHP_URL_QUERY), $parametres);
        self::assertSame('profil', $parametres['profileId']);
        self::assertSame('https://dressing.test/instagram/retour', $parametres['redirect_url']);
    }

    public function testUnCompteInstagramDuProfilEstRetrouve(): void
    {
        $client = $this->client([new MockResponse('{"accounts": [
            {"_id": "compte-tiktok", "platform": "tiktok", "username": "camille.tiktok"},
            {"_id": "compte-1", "platform": "instagram", "displayName": "Camille Martin"}
        ]}')]);

        $compte = $client->compteInstagram('compte-1');

        self::assertNotNull($compte);
        self::assertSame('compte-1', $compte->id);
        // Sans pseudo, le nom affiché sert de repli
        self::assertSame('Camille Martin', $compte->nomUtilisateur);
    }

    public function testUnePublicationEnStoryLeSignaleAZernio(): void
    {
        $corps = null;
        $client = $this->client(static function (string $methode, string $url, array $options) use (&$corps) {
            return match (true) {
                str_ends_with($url, '/media/presign') => new MockResponse(self::PRESIGN),
                str_starts_with($url, 'https://stockage.test/') => new MockResponse(''),
                default => (static function () use ($options, &$corps) {
                    $corps = json_decode($options['body'], true);

                    return new MockResponse('{"post": {"_id": "post-1", "status": "published", "platforms": [{"status": "published", "platformPostUrl": "https://www.instagram.com/p/abc/"}]}}');
                })(),
            };
        });

        $resultat = $client->publier('compte-1', self::PHOTO, FormatPublication::Story, 'Ma story');

        self::assertSame(['contentType' => 'story'], $corps['platforms'][0]['platformSpecificData']);
        self::assertSame('https://www.instagram.com/p/abc/', $resultat->url);
    }

    public function testUnRefusDInstagramRemonteSaRaison(): void
    {
        $client = $this->client([
            new MockResponse(self::PRESIGN),
            new MockResponse(''),
            new MockResponse('{"post": {"_id": "post-2", "status": "failed", "platforms": [{"status": "failed", "errorMessage": "Media download has failed"}]}}', ['http_code' => 207]),
        ]);

        $this->expectException(InstagramException::class);
        $this->expectExceptionMessage('Instagram a refusé la publication (Media download has failed).');

        $client->publier('compte-1', self::PHOTO, FormatPublication::Feed, 'Légende');
    }

    /**
     * @param callable|list<MockResponse> $reponses
     */
    private function client(callable|array $reponses): ClientZernio
    {
        return new ClientZernio(new MockHttpClient($reponses), new NullLogger(), 'sk_cle', 'profil');
    }
}
