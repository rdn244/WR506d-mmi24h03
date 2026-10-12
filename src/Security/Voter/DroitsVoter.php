<?php

namespace App\Security\Voter;

use App\Droits\ControleAcces;
use App\Droits\Droit;
use App\Droits\ProfilActif;
use App\Entity\Contenant;
use App\Entity\Logement;
use App\Entity\Look;
use App\Entity\Piece;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Applique la matrice des droits : is_granted('VOIR', $piece), #[IsGranted('MODIFIER', 'look')]…
 *
 * @extends Voter<string, Logement|Piece|Contenant|Look>
 */
final class DroitsVoter extends Voter
{
    public const VOIR = 'VOIR';
    public const MODIFIER = 'MODIFIER';

    public function __construct(
        private readonly ControleAcces $controleAcces,
        private readonly ProfilActif $profilActif,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VOIR, self::MODIFIER], true)
            && ($subject instanceof Logement || $subject instanceof Piece || $subject instanceof Contenant || $subject instanceof Look);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $requis = self::MODIFIER === $attribute ? Droit::Modifier : Droit::Voir;

        return $this->controleAcces->droitSur($this->profilActif->pour($user), $subject)->permet($requis);
    }
}
