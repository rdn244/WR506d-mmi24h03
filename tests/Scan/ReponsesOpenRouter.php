<?php

namespace App\Tests\Scan;

use App\Scan\AnalyseurObjet;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Réponses simulées d'OpenRouter en environnement de test (aucun appel réseau).
 * $scenarios force la réponse d'un modèle pour tester la chaîne de secours.
 */
final class ReponsesOpenRouter
{
    public const DETECTION = [
        'nom' => 'manteau bordeaux',
        'marque' => 'Sandro Paris',
        'type' => 'Manteau',
        'categorie' => 'vestes et manteaux',
        'matiere' => 'Laine mélangée',
        'couleur' => 'Bordeaux',
    ];

    public const QUOTA = 'quota';
    public const ERREUR_DANS_200 = 'erreur-200';
    public const TEXTE_LIBRE = 'texte';
    public const JSON_ENTOURE = 'json-entoure';
    public const CLE_REFUSEE = 'cle-refusee';
    public const RESERVE = 'reserve';
    public const CLES_ACCENTUEES = 'cles-accentuees';

    /**
     * Comportement par modèle (identifiant OpenRouter => constante ci-dessus) ; absent : réponse valide.
     *
     * @var array<string, string>
     */
    public static array $scenarios = [];

    /**
     * @var list<array{methode: string, url: string, options: array<string, mixed>}>
     */
    public static array $requetes = [];

    /**
     * Appelé avant chaque réponse : permet de changer un scénario en cours d'analyse.
     */
    public static ?\Closure $avantReponse = null;

    public static function reinitialiser(): void
    {
        self::$scenarios = [];
        self::$requetes = [];
        self::$avantReponse = null;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function __invoke(string $methode, string $url, array $options): ResponseInterface
    {
        if ('POST' !== $methode || AnalyseurObjet::URL !== $url) {
            throw new \LogicException(sprintf('Appel OpenRouter inattendu en test : %s %s', $methode, $url));
        }

        self::$requetes[] = ['methode' => $methode, 'url' => $url, 'options' => $options];
        $modele = self::corps(self::$requetes[array_key_last(self::$requetes)])['model'];
        if (null !== self::$avantReponse) {
            (self::$avantReponse)();
        }

        return match (self::$scenarios[$modele] ?? null) {
            self::QUOTA => self::json(['error' => ['code' => 429, 'message' => 'Rate limit exceeded: free-models-per-min']], 429),
            self::RESERVE => self::json(['error' => ['code' => 403, 'message' => 'only available on agentic harnesses']], 403),
            self::CLE_REFUSEE => self::json(['error' => ['code' => 401, 'message' => 'No auth credentials found']], 401),
            self::ERREUR_DANS_200 => self::json(['error' => ['code' => 502, 'message' => 'Provider returned error']]),
            self::TEXTE_LIBRE => self::reponse($modele, 'Je vois un vêtement rouge, mais je ne peux pas en dire plus.'),
            self::JSON_ENTOURE => self::reponse($modele, "Voici l'analyse :\n```json\n".json_encode(self::DETECTION, \JSON_UNESCAPED_UNICODE)."\n```"),
            self::CLES_ACCENTUEES => self::reponse($modele, '{"Nom": "Jean brut", "Marque": "Levi\'s", "Type": "Jean", "Catégories": "Bas", "Matière": "Denim", "Color": "Bleu"}'),
            default => self::reponse($modele, json_encode(self::DETECTION, \JSON_UNESCAPED_UNICODE)),
        };
    }

    /**
     * Modèles réellement interrogés, dans l'ordre.
     *
     * @return list<string>
     */
    public static function modelesInterroges(): array
    {
        return array_map(static fn (array $requete) => self::corps($requete)['model'], self::$requetes);
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

    private static function reponse(string $modele, string $texte): MockResponse
    {
        return self::json([
            'id' => 'gen-test',
            'model' => $modele,
            'choices' => [['index' => 0, 'finish_reason' => 'stop', 'message' => ['role' => 'assistant', 'content' => $texte]]],
        ]);
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
