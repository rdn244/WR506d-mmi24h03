<?php

namespace App\Instagram;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Connexion d'un compte Instagram professionnel (API « Instagram API with Instagram Login »).
 *
 * @see https://developers.facebook.com/docs/instagram-platform/instagram-api-with-instagram-login/business-login
 */
final class ClientInstagram
{
    private const URL_AUTORISATION = 'https://www.instagram.com/oauth/authorize';
    private const URL_JETON = 'https://api.instagram.com/oauth/access_token';
    private const URL_GRAPH = 'https://graph.instagram.com';
    private const VERSION_GRAPH = 'v25.0';
    private const PERMISSIONS = ['instagram_business_basic'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default::INSTAGRAM_CLIENT_ID)%')]
        private readonly ?string $clientId,
        #[Autowire('%env(default::INSTAGRAM_CLIENT_SECRET)%')]
        private readonly ?string $clientSecret,
    ) {
    }

    public function estConfigure(): bool
    {
        return '' !== (string) $this->clientId && '' !== (string) $this->clientSecret;
    }

    public function urlAutorisation(string $urlRetour, string $etat): string
    {
        return self::URL_AUTORISATION.'?'.http_build_query([
            'client_id' => $this->clientId,
            'redirect_uri' => $urlRetour,
            'response_type' => 'code',
            'scope' => implode(',', self::PERMISSIONS),
            'state' => $etat,
        ]);
    }

    /**
     * Échange le code d'autorisation contre un jeton longue durée (60 jours) et lit le profil.
     */
    public function connecter(string $code, string $urlRetour): ProfilInstagram
    {
        $jetonCourt = $this->premier($this->appeler('POST', self::URL_JETON, ['body' => [
            'client_id' => $this->clientId,
            'client_secret' => $this->clientSecret,
            'grant_type' => 'authorization_code',
            'redirect_uri' => $urlRetour,
            'code' => $code,
        ]]))['access_token'] ?? null;

        if (!\is_string($jetonCourt) || '' === $jetonCourt) {
            throw new InstagramException('Instagram n\'a pas renvoyé de jeton d\'accès.');
        }

        $jetonLong = $this->appeler('GET', self::URL_GRAPH.'/access_token', ['query' => [
            'grant_type' => 'ig_exchange_token',
            'client_secret' => $this->clientSecret,
            'access_token' => $jetonCourt,
        ]]);
        $jeton = (string) ($jetonLong['access_token'] ?? $jetonCourt);
        $expireLe = isset($jetonLong['expires_in'])
            ? new \DateTimeImmutable(sprintf('+%d seconds', (int) $jetonLong['expires_in']))
            : null;

        $profil = $this->premier($this->appeler('GET', self::URL_GRAPH.'/'.self::VERSION_GRAPH.'/me', ['query' => [
            'fields' => 'user_id,username',
            'access_token' => $jeton,
        ]]));

        if (!isset($profil['user_id'], $profil['username'])) {
            throw new InstagramException('Impossible de lire le profil Instagram.');
        }

        return new ProfilInstagram((string) $profil['user_id'], (string) $profil['username'], $jeton, $expireLe);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    private function appeler(string $methode, string $url, array $options): array
    {
        try {
            $reponse = $this->httpClient->request($methode, $url, $options);
            $donnees = $reponse->toArray(false);

            if ($reponse->getStatusCode() >= 400) {
                throw new InstagramException(sprintf('Instagram a refusé la connexion (%s).', $donnees['error_message'] ?? $donnees['error']['message'] ?? $reponse->getStatusCode()));
            }

            return $donnees;
        } catch (ExceptionInterface) {
            throw new InstagramException('Instagram ne répond pas, réessaie dans quelques instants.');
        }
    }

    /**
     * Certaines réponses sont enveloppées dans « data » (liste d'un élément), d'autres non.
     *
     * @param array<string, mixed> $donnees
     *
     * @return array<string, mixed>
     */
    private function premier(array $donnees): array
    {
        return \is_array($donnees['data'][0] ?? null) ? $donnees['data'][0] : $donnees;
    }
}
