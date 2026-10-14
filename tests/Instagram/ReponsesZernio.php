<?php

namespace App\Tests\Instagram;

use App\Instagram\ClientZernio;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Réponses simulées de l'API Zernio en environnement de test (aucun appel réseau).
 * Chaque requête est mémorisée dans $requetes pour que les tests vérifient ce qui a été envoyé.
 */
final class ReponsesZernio
{
    public const COMPTE_ID = 'compte-instagram-1';
    public const PSEUDO = 'camille.dressing';
    public const COMPTE_TIKTOK = 'compte-tiktok-1';
    public const URL_AUTORISATION = 'https://zernio.com/connect/instagram/autoriser';
    public const URL_ENVOI = 'https://stockage.zernio.test/temp/photo?signature=abc';
    public const URL_MEDIA = 'https://media.zernio.test/temp/photo.jpg';
    public const URL_PUBLICATION = 'https://www.instagram.com/p/CLook123/';

    /**
     * Légende qui simule un refus d'Instagram (réponse 207 de Zernio).
     */
    public const LEGENDE_REFUSEE = 'refus-instagram';

    /**
     * @var list<array{methode: string, url: string, options: array<string, mixed>}>
     */
    public static array $requetes = [];

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $methode, string $url, array $options): ResponseInterface
    {
        self::$requetes[] = ['methode' => $methode, 'url' => $url, 'options' => $options];
        $api = ClientZernio::URL_API;

        return match (true) {
            'GET' === $methode && str_starts_with($url, $api.'/connect/instagram?') => self::json(['authUrl' => self::URL_AUTORISATION, 'state' => 'etat-zernio']),
            'GET' === $methode && str_starts_with($url, $api.'/accounts?') => self::json(['accounts' => [
                ['_id' => self::COMPTE_ID, 'platform' => 'instagram', 'profileId' => 'profil-test', 'username' => self::PSEUDO, 'isActive' => true],
                ['_id' => self::COMPTE_TIKTOK, 'platform' => 'tiktok', 'profileId' => 'profil-test', 'username' => 'camille.tiktok', 'isActive' => true],
            ], 'hasAnalyticsAccess' => false]),
            'DELETE' === $methode && str_starts_with($url, $api.'/accounts/') => self::json(['message' => 'Account disconnected']),
            'POST' === $methode && $api.'/media/presign' === $url => self::json([
                'uploadUrl' => self::URL_ENVOI,
                'publicUrl' => self::URL_MEDIA,
                'key' => 'temp/photo.jpg',
                'expiresIn' => 3600,
            ]),
            'PUT' === $methode && self::URL_ENVOI === $url => new MockResponse('', ['http_code' => 200]),
            'POST' === $methode && $api.'/posts' === $url => self::publication($options),
            default => throw new \LogicException(sprintf('Appel HTTP inattendu en test : %s %s', $methode, $url)),
        };
    }

    /**
     * Dernière requête envoyée dont l'URL commence par $debutUrl.
     *
     * @return array{methode: string, url: string, options: array<string, mixed>}|null
     */
    public static function derniere(string $methode, string $debutUrl): ?array
    {
        foreach (array_reverse(self::$requetes) as $requete) {
            if ($methode === $requete['methode'] && str_starts_with($requete['url'], $debutUrl)) {
                return $requete;
            }
        }

        return null;
    }

    /**
     * @param array{options: array<string, mixed>} $requete
     *
     * @return array<string, mixed>
     */
    public static function corps(array $requete): array
    {
        return json_decode((string) $requete['options']['body'], true, flags: \JSON_THROW_ON_ERROR);
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function publication(array $options): MockResponse
    {
        if (self::LEGENDE_REFUSEE === self::corps(['options' => $options])['content']) {
            return self::json([
                'message' => 'Post created but publishing failed',
                'error' => 'All platforms failed',
                'post' => ['_id' => 'post-refuse', 'status' => 'failed', 'platforms' => [
                    ['platform' => 'instagram', 'status' => 'failed', 'errorMessage' => 'Media download has failed'],
                ]],
            ], 207);
        }

        return self::json(['post' => ['_id' => 'post-1', 'status' => 'published', 'platforms' => [
            ['platform' => 'instagram', 'status' => 'published', 'platformPostUrl' => self::URL_PUBLICATION],
        ]]], 201);
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private static function json(array $donnees, int $statut = 200): MockResponse
    {
        return new MockResponse(json_encode($donnees, \JSON_THROW_ON_ERROR), [
            'http_code' => $statut,
            'response_headers' => ['content-type' => 'application/json'],
        ]);
    }
}
