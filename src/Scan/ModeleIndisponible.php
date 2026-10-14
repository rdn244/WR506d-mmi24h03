<?php

namespace App\Scan;

/**
 * Un modèle n'a pas répondu correctement : on passe au suivant (usage interne à AnalyseurObjet).
 */
final class ModeleIndisponible extends \RuntimeException
{
    /**
     * @param bool $definitif le modèle refuse pour de bon : inutile de le réessayer au tour suivant
     */
    public function __construct(string $message, public readonly bool $definitif = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
