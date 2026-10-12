<?php

namespace App\Entity;

use App\Enum\FormatPublication;
use Doctrine\ORM\Mapping as ORM;

/**
 * Publication d'un look sur Instagram (via Zernio).
 */
#[ORM\Entity]
class PublicationInstagram
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'publications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Look $look;

    #[ORM\Column(length: 10, enumType: FormatPublication::class)]
    private FormatPublication $format;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $legende;

    /**
     * Identifiant de la publication chez Zernio.
     */
    #[ORM\Column(length: 64)]
    private string $zernioId;

    /**
     * Lien vers la publication ; absent tant qu'Instagram la traite.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $url;

    #[ORM\Column]
    private \DateTimeImmutable $publieeLe;

    public function __construct(Look $look, FormatPublication $format, ?string $legende, string $zernioId, ?string $url)
    {
        $this->look = $look;
        $this->format = $format;
        $this->legende = $legende;
        $this->zernioId = $zernioId;
        $this->url = $url;
        $this->publieeLe = new \DateTimeImmutable();
        $look->getPublications()->add($this);
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLook(): Look
    {
        return $this->look;
    }

    public function getFormat(): FormatPublication
    {
        return $this->format;
    }

    public function getLegende(): ?string
    {
        return $this->legende;
    }

    public function getZernioId(): string
    {
        return $this->zernioId;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function getPublieeLe(): \DateTimeImmutable
    {
        return $this->publieeLe;
    }
}
