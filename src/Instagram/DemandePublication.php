<?php

namespace App\Instagram;

use App\Enum\FormatPublication;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Données du formulaire « Publier sur Instagram ».
 */
final class DemandePublication
{
    #[Assert\NotNull(message: 'Choisis où publier ton look.')]
    public ?FormatPublication $format = FormatPublication::Feed;

    #[Assert\Length(max: 2200, maxMessage: 'La légende ne peut pas dépasser {{ limit }} caractères.')]
    public ?string $legende = null;
}
