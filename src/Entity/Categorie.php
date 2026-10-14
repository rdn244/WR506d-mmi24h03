<?php

namespace App\Entity;

use App\Repository\CategorieRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Catégorie d'objets : prédéfinie (sans propriétaire, commune à tous) ou créée par un membre.
 */
#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\UniqueConstraint(name: 'categorie_nom_unique', columns: ['proprietaire_id', 'nom'])]
class Categorie
{
    /**
     * Catégories fournies à tous, dans l'ordre d'affichage (nom => icône), insérées par migration.
     */
    public const PREDEFINIES = [
        'Hauts' => 'ph:t-shirt-duotone',
        'Bas' => 'ph:pants-duotone',
        'Robes' => 'ph:dress-duotone',
        'Vestes et manteaux' => 'ph:coat-hanger-duotone',
        'Chaussures' => 'ph:sneaker-duotone',
        'Sacs' => 'ph:handbag-duotone',
        'Accessoires' => 'ph:sunglasses-duotone',
        'Bijoux' => 'ph:diamond-duotone',
        'Livres' => 'ph:books-duotone',
        'Musique' => 'ph:vinyl-record-duotone',
        'Déco' => 'ph:lamp-duotone',
        'Autres' => 'ph:shapes-duotone',
    ];

    /**
     * Icônes proposées pour une catégorie personnelle.
     */
    public const ICONES = [
        'ph:tag-duotone',
        'ph:star-duotone',
        'ph:heart-duotone',
        'ph:baseball-cap-duotone',
        'ph:watch-duotone',
        'ph:backpack-duotone',
        'ph:suitcase-duotone',
        'ph:umbrella-duotone',
        'ph:basketball-duotone',
        'ph:game-controller-duotone',
        'ph:camera-duotone',
        'ph:palette-duotone',
        'ph:gift-duotone',
        'ph:baby-duotone',
        'ph:plant-duotone',
        'ph:package-duotone',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /**
     * Null pour une catégorie prédéfinie.
     */
    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?User $proprietaire;

    #[ORM\Column(length: 60)]
    #[Assert\NotBlank(message: 'Donne un nom à la catégorie.')]
    #[Assert\Length(max: 60, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.')]
    private ?string $nom = null;

    #[ORM\Column(length: 60)]
    #[Assert\Choice(choices: self::ICONES, message: 'Choisis une icône dans la liste.')]
    private string $icone = 'ph:tag-duotone';

    /**
     * Ordre d'affichage des catégories prédéfinies.
     */
    #[ORM\Column(options: ['default' => 0])]
    private int $ordre = 0;

    #[ORM\Column]
    private \DateTimeImmutable $creeLe;

    public function __construct(?User $proprietaire = null)
    {
        $this->proprietaire = $proprietaire;
        $this->creeLe = new \DateTimeImmutable();
    }

    /**
     * Catégorie commune à tous (sans propriétaire), à sa place dans l'ordre d'affichage.
     */
    public static function predefinie(string $nom, string $icone, int $ordre): self
    {
        $categorie = (new self())->setNom($nom)->setIcone($icone);
        $categorie->ordre = $ordre;

        return $categorie;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProprietaire(): ?User
    {
        return $this->proprietaire;
    }

    public function estPredefinie(): bool
    {
        return null === $this->proprietaire;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = null === $nom ? null : (preg_replace('/\s+/u', ' ', trim($nom)) ?: null);

        return $this;
    }

    public function getIcone(): string
    {
        return $this->icone;
    }

    public function setIcone(string $icone): static
    {
        $this->icone = $icone;

        return $this;
    }

    public function getOrdre(): int
    {
        return $this->ordre;
    }

    public function getCreeLe(): \DateTimeImmutable
    {
        return $this->creeLe;
    }
}
