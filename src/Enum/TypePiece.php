<?php

namespace App\Enum;

enum TypePiece: string
{
    case Chambre = 'chambre';
    case Salon = 'salon';
    case Cuisine = 'cuisine';
    case SalleDeBain = 'salle_de_bain';
    case Grenier = 'grenier';
    case Garage = 'garage';
    case Bureau = 'bureau';
    case Dressing = 'dressing';
    case Personnalisee = 'personnalisee';

    public function libelle(): string
    {
        return match ($this) {
            self::Chambre => 'Chambre',
            self::Salon => 'Salon',
            self::Cuisine => 'Cuisine',
            self::SalleDeBain => 'Salle de bain',
            self::Grenier => 'Grenier',
            self::Garage => 'Garage',
            self::Bureau => 'Bureau',
            self::Dressing => 'Dressing',
            self::Personnalisee => 'Pièce personnalisée',
        };
    }

    public function icone(): string
    {
        return match ($this) {
            self::Chambre => '🛏️',
            self::Salon => '🛋️',
            self::Cuisine => '🍳',
            self::SalleDeBain => '🚿',
            self::Grenier => '📦',
            self::Garage => '🚗',
            self::Bureau => '💻',
            self::Dressing => '👗',
            self::Personnalisee => '🚪',
        };
    }

    /**
     * Pièces proposées à la création du logement (hors pièce personnalisée).
     *
     * @return list<self>
     */
    public static function predefinies(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type) => self::Personnalisee !== $type));
    }
}
