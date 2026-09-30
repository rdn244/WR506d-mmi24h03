<?php

namespace App\Entity;

use App\Repository\CompteInstagramRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Compte Instagram professionnel connecté à un compte Dressing.
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
     * Un même compte Instagram ne peut être connecté qu'à un seul compte Dressing.
     */
    #[ORM\Column(length: 64, unique: true)]
    private string $instagramId;

    #[ORM\Column(length: 100)]
    private string $nomUtilisateur;

    /**
     * Jeton longue durée : ne jamais l'afficher ni le journaliser.
     */
    #[ORM\Column(type: 'text')]
    private string $jeton;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $expireLe = null;

    #[ORM\Column]
    private \DateTimeImmutable $connecteLe;

    public function __construct(User $utilisateur, string $instagramId, string $nomUtilisateur, string $jeton, ?\DateTimeImmutable $expireLe)
    {
        $this->utilisateur = $utilisateur;
        $this->instagramId = $instagramId;
        $this->mettreAJour($nomUtilisateur, $jeton, $expireLe);
    }

    public function mettreAJour(string $nomUtilisateur, string $jeton, ?\DateTimeImmutable $expireLe): void
    {
        $this->nomUtilisateur = $nomUtilisateur;
        $this->jeton = $jeton;
        $this->expireLe = $expireLe;
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

    public function getInstagramId(): string
    {
        return $this->instagramId;
    }

    public function getNomUtilisateur(): string
    {
        return $this->nomUtilisateur;
    }

    public function getUrlProfil(): string
    {
        return 'https://www.instagram.com/'.rawurlencode($this->nomUtilisateur).'/';
    }

    public function getJeton(): string
    {
        return $this->jeton;
    }

    public function getExpireLe(): ?\DateTimeImmutable
    {
        return $this->expireLe;
    }

    public function estExpire(): bool
    {
        return null !== $this->expireLe && $this->expireLe < new \DateTimeImmutable();
    }

    public function getConnecteLe(): \DateTimeImmutable
    {
        return $this->connecteLe;
    }
}
