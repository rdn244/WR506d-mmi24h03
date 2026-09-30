<?php

namespace App\Tests\Instagram;

use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Réponses simulées de l'API Instagram en environnement de test (aucun appel réseau).
 * Le code d'autorisation « code-refuse » simule un refus d'Instagram.
 */
final class ReponsesInstagram
{
    public const PSEUDO = 'camille.dressing';
    public const ID = '17841400000000001';

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $methode, string $url, array $options): ResponseInterface
    {
        return match (true) {
            str_starts_with($url, 'https://api.instagram.com/oauth/access_token') => str_contains((string) ($options['body'] ?? ''), 'code=code-refuse')
                ? new MockResponse('{"error_type":"OAuthException","code":400,"error_message":"Invalid authorization code"}', ['http_code' => 400])
                : new MockResponse(json_encode(['data' => [['access_token' => 'jeton-court', 'user_id' => self::ID, 'permissions' => 'instagram_business_basic']]])),
            str_starts_with($url, 'https://graph.instagram.com/access_token') => new MockResponse(json_encode(['access_token' => 'jeton-long', 'token_type' => 'bearer', 'expires_in' => 5184000])),
            str_starts_with($url, 'https://graph.instagram.com/v25.0/me') => new MockResponse(json_encode(['user_id' => self::ID, 'username' => self::PSEUDO])),
            default => throw new \LogicException(sprintf('Appel HTTP inattendu en test : %s %s', $methode, $url)),
        };
    }
}
