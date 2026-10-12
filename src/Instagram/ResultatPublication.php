<?php

namespace App\Instagram;

/**
 * Publication acceptée par Instagram ; l'URL manque tant qu'Instagram traite encore la photo.
 */
final readonly class ResultatPublication
{
    public function __construct(
        public string $zernioId,
        public ?string $url,
    ) {
    }
}
