<?php

namespace App\Tests\Http;

use App\Tests\Instagram\ReponsesZernio;
use App\Tests\Scan\ReponsesOpenRouter;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Aiguille chaque appel HTTP de test vers les réponses simulées de l'API visée.
 */
final readonly class ReponsesHttp
{
    public function __construct(
        private ReponsesZernio $zernio,
        private ReponsesOpenRouter $openRouter,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $methode, string $url, array $options): ResponseInterface
    {
        return str_starts_with($url, 'https://openrouter.ai/')
            ? ($this->openRouter)($methode, $url, $options)
            : ($this->zernio)($methode, $url, $options);
    }
}
