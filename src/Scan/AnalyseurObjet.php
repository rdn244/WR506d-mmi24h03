<?php

namespace App\Scan;

use App\Photo\PhotoBase64;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Reconnaissance d'un objet en photo via OpenRouter, uniquement avec des modèles gratuits.
 *
 * Les modèles sont essayés l'un après l'autre : quota atteint (429), modèle indisponible,
 * délai dépassé ou réponse inexploitable font passer au suivant. La photo part en base64
 * dans la requête : aucun hébergement ni téléchargement à attendre.
 *
 * @see https://openrouter.ai/docs
 */
final class AnalyseurObjet
{
    public const URL = 'https://openrouter.ai/api/v1/chat/completions';

    /**
     * Modèles gratuits acceptant les images (relevé du 12/10/2026) : les deux Google Gemma,
     * puis deux autres familles pour ne pas dépendre d'un seul fournisseur.
     * Écartés : Inkling (réservé aux agents de code, 403) et openrouter/free (aiguille vers un modèle de modération).
     */
    public const MODELES_PAR_DEFAUT = [
        'google/gemma-4-31b-it:free',
        'google/gemma-4-26b-a4b-it:free',
        'dots-studio/dots-3-note-preview:free',
        'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning:free',
    ];

    /**
     * Nginx coupe la requête au bout de 60 s : la chaîne de secours doit tenir en dessous.
     */
    private const BUDGET_TOTAL = 50;
    private const DELAI_PAR_MODELE = 20;
    private const DELAI_MINIMUM = 8;

    /**
     * Les quotas des modèles gratuits se libèrent vite : un second tour reprend
     * les modèles saturés après une courte pause (pas ceux qui refusent définitivement).
     */
    private const TOURS = 2;
    private const PAUSE_ENTRE_TOURS = 1.0;

    /**
     * Statuts qui ne changeront pas en réessayant (modèle réservé, retiré, requête refusée).
     */
    private const REFUS_DEFINITIFS = [400, 403, 404];

    private const CONSIGNE = 'Tu identifies l\'objet principal d\'une photo pour l\'inventaire d\'un dressing. '
        .'Réponds uniquement par un objet JSON valide, sans texte ni balise autour.';

    /**
     * %s : liste des catégories proposées, séparées par des virgules.
     */
    private const DEMANDE = 'Analyse la photo et renvoie un JSON avec exactement ces six clés, sans accent : '
        .'{"nom": nom court en français (ex. "Manteau bordeaux"), '
        .'"marque": marque lisible sur une étiquette ou un logo, sinon null, '
        .'"type": type précis au singulier en un ou deux mots (ex. Manteau, Jean, T-shirt, Baskets, Sac, Livre, Vinyle), '
        .'"categorie": une valeur recopiée exactement parmi : %s, '
        .'"matiere": matière la plus probable d\'après l\'aspect (ex. Coton, Laine, Cuir, Denim, Polyester, Papier), '
        .'"couleur": couleur dominante en un ou deux mots}. '
        .'N\'invente jamais de marque : null si aucune n\'est lisible.';

    /**
     * @var list<string>
     */
    private readonly array $modeles;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        #[Autowire('%env(default::OPENROUTER_API_KEY)%')]
        private readonly ?string $cleApi,
        #[Autowire('%env(default::OPENROUTER_MODELES)%')]
        ?string $modeles = null,
    ) {
        $liste = array_values(array_filter(array_map('trim', explode(',', (string) $modeles))));
        $this->modeles = [] === $liste ? self::MODELES_PAR_DEFAUT : $liste;
    }

    public function estConfigure(): bool
    {
        return '' !== (string) $this->cleApi;
    }

    /**
     * @param list<string> $categories noms des catégories parmi lesquelles le modèle choisit
     *
     * @throws AnalyseException quand aucun modèle n'a pu analyser la photo
     */
    public function analyser(PhotoBase64 $photo, array $categories = []): ResultatAnalyse
    {
        $debut = microtime(true);
        $dataUrl = $photo->versDataUrl();
        $demande = sprintf(self::DEMANDE, [] === $categories ? 'null' : implode(', ', $categories));
        $aEssayer = $this->modeles;

        for ($tour = 1; $tour <= self::TOURS && [] !== $aEssayer; ++$tour) {
            if ($tour > 1) {
                usleep((int) (self::PAUSE_ENTRE_TOURS * 1_000_000));
            }

            $satures = [];
            foreach ($aEssayer as $modele) {
                $restant = self::BUDGET_TOTAL - (microtime(true) - $debut);
                if ($restant < self::DELAI_MINIMUM) {
                    break 2;
                }

                try {
                    return $this->interroger($modele, $demande, $dataUrl, min(self::DELAI_PAR_MODELE, $restant));
                } catch (ModeleIndisponible $e) {
                    $this->logger->notice('Analyse photo : modèle suivant', ['modele' => $modele, 'tour' => $tour, 'raison' => $e->getMessage()]);
                    if (!$e->definitif) {
                        $satures[] = $modele;
                    }
                }
            }
            $aEssayer = $satures;
        }

        throw new AnalyseException('L\'analyse n\'a pas abouti : les modèles gratuits sont saturés. Remplis les champs à la main ou réessaie dans une minute.');
    }

    /**
     * @throws ModeleIndisponible pour passer au modèle suivant
     * @throws AnalyseException   pour tout arrêter (clé refusée)
     */
    private function interroger(string $modele, string $demande, string $dataUrl, float $delai): ResultatAnalyse
    {
        try {
            $reponse = $this->httpClient->request('POST', self::URL, [
                'auth_bearer' => $this->cleApi,
                'headers' => ['X-Title' => 'Dressing'],
                'json' => [
                    'model' => $modele,
                    'messages' => [
                        ['role' => 'system', 'content' => self::CONSIGNE],
                        ['role' => 'user', 'content' => [
                            ['type' => 'text', 'text' => $demande],
                            ['type' => 'image_url', 'image_url' => ['url' => $dataUrl]],
                        ]],
                    ],
                    'temperature' => 0.2,
                    'max_tokens' => 1200,
                    // Sans réflexion préalable : réponse en 1 à 2 s au lieu de 15 à 25 s, et plus de
                    // réponse vide quand la réflexion épuisait tous les jetons (Dots, Nemotron)
                    'reasoning' => ['enabled' => false],
                ],
                'timeout' => $delai,
                'max_duration' => $delai,
            ]);
            $statut = $reponse->getStatusCode();
            $donnees = $reponse->toArray(false);
        } catch (ExceptionInterface $e) {
            throw new ModeleIndisponible('injoignable ou trop lent : '.$e->getMessage(), previous: $e);
        }

        if (401 === $statut) {
            $this->logger->error('Clé OpenRouter refusée');

            throw new AnalyseException('La clé OpenRouter est refusée : vérifie OPENROUTER_API_KEY.');
        }

        // Une erreur peut aussi arriver avec un statut 200 : le corps fait foi
        if ($statut >= 400 || isset($donnees['error'])) {
            $code = (int) ($donnees['error']['code'] ?? $statut);

            throw new ModeleIndisponible(sprintf('erreur %d : %s', $code, $donnees['error']['message'] ?? 'inconnue'), \in_array($code, self::REFUS_DEFINITIFS, true));
        }

        $champs = self::extraireJson(self::texteDe($donnees));
        $resultat = null === $champs ? null : ResultatAnalyse::depuisReponse($champs, (string) ($donnees['model'] ?? $modele));

        if (null === $resultat || $resultat->estVide()) {
            throw new ModeleIndisponible('réponse inexploitable');
        }

        return $resultat;
    }

    /**
     * @param array<string, mixed> $donnees
     */
    private static function texteDe(array $donnees): string
    {
        $contenu = $donnees['choices'][0]['message']['content'] ?? '';

        // Certains fournisseurs renvoient une liste de morceaux plutôt qu'une chaîne
        if (\is_array($contenu)) {
            $contenu = implode('', array_map(static fn ($morceau) => \is_array($morceau) ? (string) ($morceau['text'] ?? '') : '', $contenu));
        }

        return \is_string($contenu) ? $contenu : '';
    }

    /**
     * Premier objet JSON du texte, même entouré de prose ou de balises ```json.
     *
     * @return array<string, mixed>|null
     */
    private static function extraireJson(string $texte): ?array
    {
        $debut = strpos($texte, '{');
        $fin = strrpos($texte, '}');
        if (false === $debut || false === $fin || $fin < $debut) {
            return null;
        }

        $json = json_decode(substr($texte, $debut, $fin - $debut + 1), true);

        return \is_array($json) ? $json : null;
    }
}
