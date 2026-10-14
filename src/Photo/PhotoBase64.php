<?php

namespace App\Photo;

/**
 * Photo reçue en « data URL » base64 (prise par le scan, réduite dans le navigateur).
 * Le contenu est toujours vérifié : c'est une donnée externe.
 */
final readonly class PhotoBase64
{
    public const POIDS_MAX = 5 * 1024 * 1024;
    public const COTE_MAX = 4096;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    private function __construct(
        public string $binaire,
        public string $typeMime,
        public string $extension,
    ) {
    }

    /**
     * @throws \InvalidArgumentException si ce n'est pas une image JPEG, PNG ou WebP valide
     */
    public static function depuisDataUrl(string $dataUrl): self
    {
        if (!preg_match('#^data:(image/(?:jpeg|png|webp));base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $morceaux)) {
            throw new \InvalidArgumentException('La photo doit être une image JPEG, PNG ou WebP.');
        }

        // Taille estimée avant décodage : on évite de décoder un contenu démesuré
        if (\strlen($morceaux[2]) > self::POIDS_MAX * 4 / 3 + 4) {
            throw new \InvalidArgumentException('La photo est trop lourde (5 Mo maximum).');
        }

        $binaire = base64_decode($morceaux[2], true);
        $infos = false === $binaire ? false : @getimagesizefromstring($binaire);
        if (false === $infos || $infos['mime'] !== $morceaux[1]) {
            throw new \InvalidArgumentException('La photo est illisible.');
        }

        if ($infos[0] > self::COTE_MAX || $infos[1] > self::COTE_MAX) {
            throw new \InvalidArgumentException('La photo est trop grande.');
        }

        return new self($binaire, $infos['mime'], self::EXTENSIONS[$infos['mime']]);
    }

    /**
     * Data URL normalisée, prête à être envoyée à un modèle de vision.
     */
    public function versDataUrl(): string
    {
        return 'data:'.$this->typeMime.';base64,'.base64_encode($this->binaire);
    }
}
