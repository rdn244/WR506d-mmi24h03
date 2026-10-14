<?php

namespace App\Entity;

use App\Repository\ObjetRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Objet de l'inventaire (vêtement, livre, vinyle…), éventuellement rangé dans un contenant.
 */
#[ORM\Entity(repositoryClass: ObjetRepository::class)]
class Objet
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $proprietaire;

    /**
     * Rangement de l'objet ; il redevient « pas encore rangé » si le rangement disparaît.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Contenant $contenant = null;

    #[ORM\Column(length: 120)]
    #[Assert\NotBlank(message: 'Donne un nom à l\'objet.')]
    #[Assert\Length(max: 120, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80, maxMessage: 'La marque ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $marque = null;

    /**
     * Grande famille (Hauts, Chaussures…) ; l'objet redevient « sans catégorie » si elle est supprimée.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Categorie $categorie = null;

    /**
     * Type précis, en texte libre (Manteau, T-shirt, Vinyle 33T…).
     */
    #[ORM\Column(length: 60, nullable: true)]
    #[Assert\Length(max: 60, maxMessage: 'Le type ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $type = null;

    #[ORM\Column(length: 80, nullable: true)]
    #[Assert\Length(max: 80, maxMessage: 'La matière ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $matiere = null;

    #[ORM\Column(length: 40, nullable: true)]
    #[Assert\Length(max: 40, maxMessage: 'La couleur ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $couleur = null;

    /**
     * Nom du fichier dans le dossier des photos d'objets (voir StockagePhotos).
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $photo = null;

    /**
     * Objet personnel : visible par son propriétaire seul (ligne « objet personnel » de la matrice).
     */
    #[ORM\Column(options: ['default' => false])]
    private bool $personnel = false;

    /**
     * Modèle d'IA qui a pré-rempli la fiche, s'il y en a eu un.
     */
    #[ORM\Column(length: 120, nullable: true)]
    #[Assert\Length(max: 120)]
    private ?string $modeleIa = null;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    public function __construct(User $proprietaire)
    {
        $this->proprietaire = $proprietaire;
        $this->creeLe = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProprietaire(): User
    {
        return $this->proprietaire;
    }

    public function getContenant(): ?Contenant
    {
        return $this->contenant;
    }

    public function setContenant(?Contenant $contenant): static
    {
        $this->contenant = $contenant;

        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = self::nettoyer($nom);

        return $this;
    }

    public function getMarque(): ?string
    {
        return $this->marque;
    }

    public function setMarque(?string $marque): static
    {
        $this->marque = self::nettoyer($marque);

        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = self::nettoyer($type);

        return $this;
    }

    public function getMatiere(): ?string
    {
        return $this->matiere;
    }

    public function setMatiere(?string $matiere): static
    {
        $this->matiere = self::nettoyer($matiere);

        return $this;
    }

    public function getCouleur(): ?string
    {
        return $this->couleur;
    }

    public function setCouleur(?string $couleur): static
    {
        $this->couleur = self::nettoyer($couleur);

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function isPersonnel(): bool
    {
        return $this->personnel;
    }

    public function setPersonnel(bool $personnel): static
    {
        $this->personnel = $personnel;

        return $this;
    }

    public function getModeleIa(): ?string
    {
        return $this->modeleIa;
    }

    public function setModeleIa(?string $modeleIa): static
    {
        $this->modeleIa = self::nettoyer($modeleIa);

        return $this;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }

    private static function nettoyer(?string $valeur): ?string
    {
        $valeur = null === $valeur ? '' : trim($valeur);

        return '' === $valeur ? null : $valeur;
    }
}
