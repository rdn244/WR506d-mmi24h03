<?php

namespace App\Entity;

use App\Repository\CompteInstagramRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compte Instagram professionnel connecté à un compte Dressing (via Zernio).
 */
#[ORM\Entity(repositoryClass: CompteInstagramRepository::class)]
class CompteInstagram
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    private User $utilisateur;

    /**
     * Identifiant du compte chez Zernio : un compte Instagram n'est relié qu'à un seul compte Dressing.
     */
    #[ORM\Column(length: 64, unique: true)]
    private string $zernioId;

    #[ORM\Column(length: 100)]
    private string $nomUtilisateur;

    #[ORM\Column]
    private \DateTimeImmutable $connecteLe;

    public function __construct(User $utilisateur, string $zernioId, string $nomUtilisateur)
    {
        $this->utilisateur = $utilisateur;
        $this->zernioId = $zernioId;
        $this->mettreAJour($nomUtilisateur);
    }

    public function mettreAJour(string $nomUtilisateur): void
    {
        $this->nomUtilisateur = $nomUtilisateur;
        $this->connecteLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): User
    {
        return $this->utilisateur;
    }

    public function getZernioId(): string
    {
        return $this->zernioId;
    }

    public function getNomUtilisateur(): string
    {
        return $this->nomUtilisateur;
    }

    public function getUrlProfil(): string
    {
        return 'https://www.instagram.com/'.rawurlencode($this->nomUtilisateur).'/';
    }

    public function getConnecteLe(): \DateTimeImmutable
    {
        return $this->connecteLe;
    }
}
