<?php

namespace App\Entity;

use App\Enum\TypePiece;
use App\Repository\PieceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PieceRepository::class)]
class Piece
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 20, enumType: TypePiece::class)]
    private TypePiece $type = TypePiece::Personnalisee;

    /**
     * Une pièce partagée devient visible des comptes liés (matrice des droits).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $partagee = false;

    #[ORM\ManyToOne(inversedBy: 'pieces')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Logement $logement = null;

    /**
     * @var Collection<int, Contenant>
     */
    #[ORM\OneToMany(targetEntity: Contenant::class, mappedBy: 'piece', cascade: ['persist'], orphanRemoval: true)]
    #[ORM\OrderBy(['id' => 'ASC'])]
    private Collection $contenants;

    public function __construct()
    {
        $this->contenants = new ArrayCollection();
    }

    public static function depuisType(TypePiece $type): self
    {
        return (new self())->setType($type)->setNom($type->libelle());
    }

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

    public function getType(): TypePiece
    {
        return $this->type;
    }

    public function setType(TypePiece $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getIcone(): string
    {
        return $this->type->icone();
    }

    public function isPartagee(): bool
    {
        return $this->partagee;
    }

    public function setPartagee(bool $partagee): static
    {
        $this->partagee = $partagee;

        return $this;
    }

    public function getLogement(): ?Logement
    {
        return $this->logement;
    }

    public function setLogement(?Logement $logement): static
    {
        $this->logement = $logement;

        return $this;
    }

    /**
     * @return Collection<int, Contenant>
     */
    public function getContenants(): Collection
    {
        return $this->contenants;
    }

    public function addContenant(Contenant $contenant): static
    {
        if (!$this->contenants->contains($contenant)) {
            $this->contenants->add($contenant);
            $contenant->setPiece($this);
        }

        return $this;
    }

    public function removeContenant(Contenant $contenant): static
    {
        $this->contenants->removeElement($contenant);

        return $this;
    }
}
