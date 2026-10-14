<?php

namespace App\Scan;

/**
 * Champs détectés sur la photo. La réponse d'un modèle n'est jamais fiable :
 * chaque valeur est nettoyée et tronquée à la taille de la colonne correspondante.
 */
final readonly class ResultatAnalyse
{
    /**
     * Longueur maximale de chaque champ (colonnes de l'entité Objet).
     */
    public const LONGUEURS = [
        'nom' => 120,
        'marque' => 80,
        'type' => 60,
        'categorie' => 60,
        'matiere' => 80,
        'couleur' => 40,
    ];

    /**
     * Clés que les modèles emploient à la place des nôtres (accents, anglais).
     */
    private const SYNONYMES = [
        'name' => 'nom',
        'brand' => 'marque',
        'category' => 'categorie',
        'categories' => 'categorie',
        'famille' => 'categorie',
        'kind' => 'type',
        'material' => 'matiere',
        'materiau' => 'matiere',
        'matieres' => 'matiere',
        'color' => 'couleur',
        'colour' => 'couleur',
    ];

    /**
     * Réponses qui veulent dire « je ne sais pas ».
     */
    private const INCONNUES = ['null', 'none', 'n/a', 'na', 'inconnu', 'inconnue', 'unknown', 'aucune', 'aucun', '-'];

    private function __construct(
        public ?string $nom,
        public ?string $marque,
        public ?string $type,
        public ?string $categorie,
        public ?string $matiere,
        public ?string $couleur,
        public string $modele,
    ) {
    }

    /**
     * @param array<string, mixed> $champs
     */
    public static function depuisReponse(array $champs, string $modele): self
    {
        $champs = self::normaliserCles($champs);
        $valeur = static function (string $cle) use ($champs): ?string {
            $brut = $champs[$cle] ?? null;
            if (!\is_string($brut)) {
                return null;
            }

            $texte = trim(preg_replace('/\s+/u', ' ', strip_tags($brut)) ?? '');
            if ('' === $texte || \in_array(mb_strtolower($texte), self::INCONNUES, true)) {
                return null;
            }

            return mb_substr(mb_strtoupper(mb_substr($texte, 0, 1)).mb_substr($texte, 1), 0, self::LONGUEURS[$cle]);
        };

        return new self($valeur('nom'), $valeur('marque'), $valeur('type'), $valeur('categorie'), $valeur('matiere'), $valeur('couleur'), $modele);
    }

    /**
     * « Catégorie », « matière », « category »… deviennent nos clés.
     *
     * @param array<string, mixed> $champs
     *
     * @return array<string, mixed>
     */
    private static function normaliserCles(array $champs): array
    {
        $normalisees = [];
        foreach ($champs as $cle => $valeur) {
            $cle = strtr(mb_strtolower(trim((string) $cle)), ['é' => 'e', 'è' => 'e', 'ê' => 'e', 'à' => 'a']);
            $normalisees[self::SYNONYMES[$cle] ?? $cle] ??= $valeur;
        }

        return $normalisees;
    }

    public function estVide(): bool
    {
        return null === $this->nom && null === $this->type && null === $this->categorie;
    }

    /**
     * Champs du formulaire. La catégorie n'est retenue que si elle figure dans la liste proposée
     * (comparaison sans casse) ; sinon, un modèle qui y a mis un type précis le voit repris en type.
     *
     * @param array<int, string> $categories identifiant => nom des catégories disponibles
     *
     * @return array{nom: ?string, marque: ?string, type: ?string, matiere: ?string, couleur: ?string, categorie: ?int}
     */
    public function champs(array $categories = []): array
    {
        $categorieId = null;
        foreach ($categories as $id => $nom) {
            if (null !== $this->categorie && mb_strtolower($nom) === mb_strtolower($this->categorie)) {
                $categorieId = $id;
                break;
            }
        }
        $type = $this->type ?? (null === $categorieId ? $this->categorie : null);

        return [
            'nom' => $this->nom ?? $type,
            'marque' => $this->marque,
            'type' => $type,
            'matiere' => $this->matiere,
            'couleur' => $this->couleur,
            'categorie' => $categorieId,
        ];
    }
}
