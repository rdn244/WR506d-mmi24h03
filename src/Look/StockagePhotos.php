<?php

namespace App\Look;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Photos des looks, rangées hors de public/ : elles ne sont servies qu'après contrôle des droits.
 */
final class StockagePhotos
{
    public function __construct(
        #[Autowire('%kernel.project_dir%/var/uploads/%kernel.environment%/looks')]
        private readonly string $dossier,
        private readonly Filesystem $filesystem = new Filesystem(),
    ) {
    }

    /**
     * Range la photo sous un nom aléatoire et renvoie ce nom.
     */
    public function enregistrer(UploadedFile $fichier): string
    {
        $nom = bin2hex(random_bytes(16)).'.'.($fichier->guessExtension() ?? 'jpg');
        $fichier->move($this->dossier, $nom);

        return $nom;
    }

    public function chemin(string $nom): string
    {
        return $this->dossier.'/'.basename($nom);
    }

    public function existe(string $nom): bool
    {
        return is_file($this->chemin($nom));
    }

    public function supprimer(string $nom): void
    {
        $this->filesystem->remove($this->chemin($nom));
    }
}
