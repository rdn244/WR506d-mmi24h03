<?php

namespace App\Entity;

use App\Enum\TypeContenant;
use App\Repository\ContenantRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ContenantRepository::class)]
class Contenant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Donne un nom au contenant.')]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 20, enumType: TypeContenant::class)]
    #[Assert\NotNull]
    private ?TypeContenant $type = TypeContenant::Armoire;

    #[ORM\ManyToOne(inversedBy: 'contenants')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Piece $piece = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = null === $nom ? null : trim($nom);

        return $this;
    }

    public function getType(): ?TypeContenant
    {
        return $this->type;
    }

    public function setType(?TypeContenant $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getIcone(): string
    {
        return ($this->type ?? TypeContenant::Autre)->icone();
    }

    public function getPiece(): ?Piece
    {
        return $this->piece;
    }

    public function setPiece(?Piece $piece): static
    {
        $this->piece = $piece;

        return $this;
    }
}
