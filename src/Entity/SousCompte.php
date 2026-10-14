<?php

namespace App\Entity;

use App\Enum\NiveauSousCompte;
use App\Repository\SousCompteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * Profil secondaire rattaché à un compte principal, sans identifiants propres (maquettes 27 à 29).
 */
#[ORM\Entity(repositoryClass: SousCompteRepository::class)]
class SousCompte
{
    public const AGE_MAJORITE = 18;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'sousComptes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?User $comptePrincipal = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Renseigne le prénom.')]
    #[Assert\Length(max: 50)]
    private ?string $prenom = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'Renseigne la date de naissance.')]
    #[Assert\LessThanOrEqual('today', message: 'La date de naissance ne peut pas être dans le futur.')]
    private ?\DateTimeImmutable $dateNaissance = null;

    /**
     * Uniquement pour les sous-comptes enfants.
     */
    #[ORM\Column(length: 20, nullable: true, enumType: NiveauSousCompte::class)]
    private ?NiveauSousCompte $niveau = NiveauSousCompte::Simplifie;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    public function __construct()
    {
        $this->creeLe = new \DateTimeImmutable();
    }

    #[Assert\Callback]
    public function validerNiveau(ExecutionContextInterface $context): void
    {
        if (null !== $this->dateNaissance && !$this->estMajeur() && null === $this->niveau) {
            $context->buildViolation('Choisis les permissions du profil.')->atPath('niveau')->addViolation();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getComptePrincipal(): ?User
    {
        return $this->comptePrincipal;
    }

    public function setComptePrincipal(?User $comptePrincipal): static
    {
        $this->comptePrincipal = $comptePrincipal;

        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = null === $prenom ? null : trim($prenom);

        return $this;
    }

    public function getInitiale(): string
    {
        return mb_strtoupper(mb_substr((string) $this->prenom, 0, 1));
    }

    public function getDateNaissance(): ?\DateTimeImmutable
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeImmutable $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;

        return $this;
    }

    public function estMajeur(?\DateTimeImmutable $aujourdhui = null): bool
    {
        if (null === $this->dateNaissance) {
            return false;
        }

        return $this->dateNaissance->diff($aujourdhui ?? new \DateTimeImmutable('today'))->y >= self::AGE_MAJORITE;
    }

    public function getNiveau(): ?NiveauSousCompte
    {
        return $this->estMajeur() ? null : $this->niveau;
    }

    public function setNiveau(?NiveauSousCompte $niveau): static
    {
        $this->niveau = $niveau;

        return $this;
    }

    public function getLibelleType(): string
    {
        return $this->estMajeur() ? 'Sous-compte +18' : 'Sous-compte enfant';
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }
}
