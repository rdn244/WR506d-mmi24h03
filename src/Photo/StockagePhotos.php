<?php

namespace App\Photo;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Photos rangées hors de public/ : elles ne sont servies qu'après contrôle des droits.
 * Un service par dossier (looks, objets) : voir config/services.yaml.
 */
#[Exclude]
final class StockagePhotos
{
    public function __construct(
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

    /**
     * Range une photo déjà décodée (scan) sous un nom aléatoire et renvoie ce nom.
     */
    public function enregistrerContenu(PhotoBase64 $photo): string
    {
        $nom = bin2hex(random_bytes(16)).'.'.$photo->extension;
        $this->filesystem->dumpFile($this->chemin($nom), $photo->binaire);

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

    /**
     * Réponse qui sert la photo : à n'appeler qu'après le contrôle des droits.
     */
    public function reponse(string $nom): BinaryFileResponse
    {
        if (!$this->existe($nom)) {
            throw new NotFoundHttpException('Photo introuvable.');
        }

        $reponse = new BinaryFileResponse($this->chemin($nom));
        // Photo soumise aux droits : jamais en cache partagé
        $reponse->setPrivate();
        $reponse->setMaxAge(3600);
        $reponse->headers->set('X-Content-Type-Options', 'nosniff');

        return $reponse;
    }

    public function supprimer(string $nom): void
    {
        $this->filesystem->remove($this->chemin($nom));
    }
}
