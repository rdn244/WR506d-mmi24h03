<?php

namespace App\Instagram;

use App\Enum\FormatPublication;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Connexion de comptes Instagram et publication via l'API Zernio.
 *
 * Tous les comptes Instagram sont rattachés au même profil Zernio (ZERNIO_PROFILE_ID).
 * Les photos sont d'abord envoyées chez Zernio (URL présignée) : Instagram ne télécharge
 * donc jamais rien depuis Dressing, ce qui fonctionne aussi en local.
 *
 * @see https://docs.zernio.com
 */
final class ClientZernio
{
    public const URL_API = 'https://zernio.com/api/v1';

    /**
     * Une publication immédiate attend la réponse d'Instagram : délai plus long.
     */
    private const DELAI_PUBLICATION = 90;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::ZERNIO_API_KEY)%')]
        private readonly ?string $cleApi,
        #[Autowire('%env(default::ZERNIO_PROFILE_ID)%')]
        private readonly ?string $profilId,
    ) {
    }

    public function estConfigure(): bool
    {
        return '' !== (string) $this->cleApi && '' !== (string) $this->profilId;
    }

    /**
     * Adresse de la page d'autorisation Instagram ; Zernio renvoie ensuite le navigateur vers $urlRetour.
     */
    public function urlConnexion(string $urlRetour): string
    {
        $url = $this->appeler('GET', '/connect/instagram', ['query' => [
            'profileId' => $this->profilId,
            'redirect_url' => $urlRetour,
        ]])['authUrl'] ?? null;

        if (!\is_string($url) || !str_starts_with($url, 'https://')) {
            throw new InstagramException('Zernio n\'a pas renvoyé d\'adresse de connexion.');
        }

        return $url;
    }

    /**
     * Compte Instagram du profil Zernio, ou null s'il n'y est pas rattaché.
     * Le retour de connexion n'est pas signé : l'identifiant reçu doit toujours être vérifié ici.
     */
    public function compteInstagram(string $compteId): ?CompteZernio
    {
        $comptes = $this->appeler('GET', '/accounts', ['query' => [
            'profileId' => $this->profilId,
            'platform' => 'instagram',
        ]])['accounts'] ?? [];

        foreach (\is_array($comptes) ? $comptes : [] as $compte) {
            if (\is_array($compte) && $compteId === ($compte['_id'] ?? null) && 'instagram' === ($compte['platform'] ?? null)) {
                return new CompteZernio($compteId, (string) ($compte['username'] ?? $compte['displayName'] ?? ''));
            }
        }

        return null;
    }

    /**
     * Retire le compte du profil Zernio ; un compte déjà retiré n'est pas une erreur.
     */
    public function deconnecter(string $compteId): void
    {
        $this->appeler('DELETE', '/accounts/'.rawurlencode($compteId), [], [404]);
    }

    /**
     * Publie immédiatement une photo sur le compte Instagram.
     */
    public function publier(string $compteId, string $cheminPhoto, FormatPublication $format, string $legende): ResultatPublication
    {
        $urlPublique = $this->envoyerPhoto($cheminPhoto);

        $plateforme = ['platform' => 'instagram', 'accountId' => $compteId];
        if (FormatPublication::Story === $format) {
            $plateforme['platformSpecificData'] = ['contentType' => 'story'];
        }

        $publication = $this->appeler('POST', '/posts', [
            'json' => [
                'content' => $legende,
                'mediaItems' => [['type' => 'image', 'url' => $urlPublique]],
                'platforms' => [$plateforme],
                'publishNow' => true,
            ],
            'timeout' => self::DELAI_PUBLICATION,
        ])['post'] ?? [];

        $instagram = $publication['platforms'][0] ?? [];
        if ('failed' === ($publication['status'] ?? null) || 'failed' === ($instagram['status'] ?? null)) {
            $raison = $instagram['errorMessage'] ?? 'raison inconnue';
            $this->logger->warning('Publication Instagram refusée', ['raison' => $raison, 'publication' => $publication['_id'] ?? null]);

            throw new InstagramException(sprintf('Instagram a refusé la publication (%s).', $raison));
        }

        // Lien affiché tel quel dans l'interface : uniquement une adresse https
        $url = $instagram['platformPostUrl'] ?? null;

        return new ResultatPublication((string) ($publication['_id'] ?? ''), \is_string($url) && str_starts_with($url, 'https://') ? $url : null);
    }

    /**
     * Envoie la photo dans le stockage de Zernio et renvoie son URL publique.
     */
    private function envoyerPhoto(string $chemin): string
    {
        $contenu = is_file($chemin) ? file_get_contents($chemin) : false;
        if (false === $contenu) {
            throw new InstagramException('La photo du look est introuvable.');
        }
        $typeMime = MimeTypes::getDefault()->guessMimeType($chemin) ?? 'image/jpeg';

        $envoi = $this->appeler('POST', '/media/presign', ['json' => [
            'filename' => basename($chemin),
            'contentType' => $typeMime,
            'size' => \strlen($contenu),
        ]]);

        if (!\is_string($envoi['uploadUrl'] ?? null) || !\is_string($envoi['publicUrl'] ?? null)) {
            throw new InstagramException('Zernio n\'a pas fourni d\'adresse d\'envoi pour la photo.');
        }

        try {
            // Le stockage refuse l'en-tête Authorization : requête directe, sans la clé d'API
            $statut = $this->httpClient->request('PUT', $envoi['uploadUrl'], [
                'headers' => ['Content-Type' => $typeMime],
                'body' => $contenu,
            ])->getStatusCode();
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Envoi de la photo vers Zernio impossible', ['exception' => $e]);
            $statut = 0;
        }

        if ($statut < 200 || $statut >= 300) {
            throw new InstagramException('L\'envoi de la photo a échoué, réessaie dans quelques instants.');
        }

        return $envoi['publicUrl'];
    }

    /**
     * @param array<string, mixed> $options
     * @param list<int>            $statutsIgnores statuts d'erreur acceptés sans exception (réponse vide)
     *
     * @return array<string, mixed>
     */
    private function appeler(string $methode, string $chemin, array $options = [], array $statutsIgnores = []): array
    {
        $options['auth_bearer'] = $this->cleApi;

        try {
            $reponse = $this->httpClient->request($methode, self::URL_API.$chemin, $options);
            $statut = $reponse->getStatusCode();

            if (\in_array($statut, $statutsIgnores, true)) {
                return [];
            }

            $donnees = '' === $reponse->getContent(false) ? [] : $reponse->toArray(false);
        } catch (ExceptionInterface $e) {
            $this->logger->warning('Zernio injoignable', ['methode' => $methode, 'chemin' => $chemin, 'exception' => $e]);

            throw new InstagramException('Zernio ne répond pas, réessaie dans quelques instants.');
        }

        if ($statut >= 400) {
            $message = \is_string($donnees['error'] ?? null) ? $donnees['error'] : ($donnees['message'] ?? $statut);
            $this->logger->warning('Zernio a refusé la demande', ['methode' => $methode, 'chemin' => $chemin, 'statut' => $statut, 'message' => $message]);

            throw new InstagramException(sprintf('Zernio a refusé la demande (%s).', $message));
        }

        return $donnees;
    }
}
