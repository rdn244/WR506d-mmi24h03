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

    /**
     * Nom de l'icône Symfony UX Icons (jeu Phosphor).
     */
    public function icone(): string
    {
        return match ($this) {
            self::Chambre => 'ph:bed-duotone',
            self::Salon => 'ph:couch-duotone',
            self::Cuisine => 'ph:cooking-pot-duotone',
            self::SalleDeBain => 'ph:bathtub-duotone',
            self::Grenier => 'ph:package-duotone',
            self::Garage => 'ph:garage-duotone',
            self::Bureau => 'ph:desk-duotone',
            self::Dressing => 'ph:coat-hanger-duotone',
            self::Personnalisee => 'ph:door-duotone',
        };
    }

    /**
     * Pièces proposées à la création du logement (hors pièce personnalisée).
     *
     * @return list<self>
     */
    public static function predefinies(): array
    {
        $types = [];
        foreach (self::cases() as $type) {
            if (self::Personnalisee !== $type) {
                $types[] = $type;
            }
        }

        return $types;
    }
}
