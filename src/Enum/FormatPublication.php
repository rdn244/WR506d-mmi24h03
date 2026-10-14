<?php

namespace App\Enum;

/**
 * Emplacement d'une publication Instagram.
 */
enum FormatPublication: string
{
    case Feed = 'feed';
    case Story = 'story';

    /**
     * Rapports largeur / hauteur acceptés dans le feed (de 9:16 à 1,91:1, doc Zernio).
     */
    private const RAPPORT_FEED_MIN = 9 / 16;
    private const RAPPORT_FEED_MAX = 1.91;

    public function libelle(): string
    {
        return match ($this) {
            self::Feed => 'Feed',
            self::Story => 'Story',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Feed => 'ph:device-mobile-duotone',
            self::Story => 'ph:timer-duotone',
        };
    }

    /**
     * Une story accepte tout format (Instagram recadre) ; le feed refuse les photos trop étirées.
     */
    public function accepteDimensions(int $largeur, int $hauteur): bool
    {
        if (self::Story === $this) {
            return true;
        }

        if ($largeur <= 0 || $hauteur <= 0) {
            return false;
        }

        $rapport = $largeur / $hauteur;

        return $rapport >= self::RAPPORT_FEED_MIN && $rapport <= self::RAPPORT_FEED_MAX;
    }
}
