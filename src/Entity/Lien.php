<?php

namespace App\Entity;

use App\Enum\StatutLien;
use App\Enum\TypeLien;
use App\Repository\LienRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Lien orienté entre deux utilisateurs : amitié, compte lié, abonnement ou blocage.
 */
#[ORM\Entity(repositoryClass: LienRepository::class)]
#[ORM\UniqueConstraint(name: 'lien_unique', columns: ['type', 'demandeur_id', 'destinataire_id'])]
class Lien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, enumType: TypeLien::class)]
    private TypeLien $type;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $demandeur;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $destinataire;

    #[ORM\Column(length: 20, enumType: StatutLien::class)]
    private StatutLien $statut;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    public function __construct(TypeLien $type, User $demandeur, User $destinataire)
    {
        $this->type = $type;
        $this->demandeur = $demandeur;
        $this->destinataire = $destinataire;
        // Seules les amitiés et les comptes liés attendent une acceptation
        $this->statut = \in_array($type, [TypeLien::Ami, TypeLien::CompteLie], true) ? StatutLien::EnAttente : StatutLien::Accepte;
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): TypeLien
    {
        return $this->type;
    }

    public function getDemandeur(): User
    {
        return $this->demandeur;
    }

    public function getDestinataire(): User
    {
        return $this->destinataire;
    }

    public function autreQue(User $user): User
    {
        return $this->demandeur === $user ? $this->destinataire : $this->demandeur;
    }

    public function getStatut(): StatutLien
    {
        return $this->statut;
    }

    public function estAccepte(): bool
    {
        return StatutLien::Accepte === $this->statut;
    }

    public function accepter(): static
    {
        $this->statut = StatutLien::Accepte;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }
}
