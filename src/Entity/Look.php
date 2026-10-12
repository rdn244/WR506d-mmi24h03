<?php

namespace App\Entity;

use App\Repository\LookRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Tenue photographiée par un membre, publiable sur Instagram.
 */
#[ORM\Entity(repositoryClass: LookRepository::class)]
class Look
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $proprietaire;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Donne un titre à ton look.')]
    #[Assert\Length(max: 80, maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $titre = null;

    /**
     * Reprise comme légende Instagram par défaut (2 200 caractères au plus).
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2200, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $description = null;

    /**
     * Nom du fichier dans le dossier des photos (voir StockagePhotos).
     */
    #[ORM\Column(length: 64)]
    private ?string $photo = null;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    /**
     * @var Collection<int, PublicationInstagram>
     */
    #[ORM\OneToMany(targetEntity: PublicationInstagram::class, mappedBy: 'look')]
    #[ORM\OrderBy(['publieeLe' => 'DESC'])]
    private Collection $publications;

    public function __construct(User $proprietaire)
    {
        $this->proprietaire = $proprietaire;
        $this->creeLe = new \DateTimeImmutable();
        $this->publications = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProprietaire(): User
    {
        return $this->proprietaire;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = null === $titre ? null : trim($titre);

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $description = null === $description ? '' : trim($description);
        $this->description = '' === $description ? null : $description;

        return $this;
    }

    /**
     * Légende proposée à la publication : la description, sinon le titre.
     */
    public function getLegendeParDefaut(): string
    {
        return (string) ($this->description ?? $this->titre);
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    /**
     * @return Collection<int, PublicationInstagram>
     */
    public function getPublications(): Collection
    {
        return $this->publications;
    }
}
