<?php

namespace App\Security\Voter;

use App\Entity\Contenant;
use App\Entity\Logement;
use App\Entity\Piece;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Seul le propriétaire d'un logement peut consulter et gérer ses pièces et contenants.
 *
 * @extends Voter<string, Logement|Piece|Contenant>
 */
final class ProprietaireVoter extends Voter
{
    public const GERER = 'GERER';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::GERER === $attribute
            && ($subject instanceof Logement || $subject instanceof Piece || $subject instanceof Contenant);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $logement = match (true) {
            $subject instanceof Logement => $subject,
            $subject instanceof Piece => $subject->getLogement(),
            $subject instanceof Contenant => $subject->getPiece()?->getLogement(),
        };

        return null !== $logement && $logement->getProprietaire() === $user;
    }
}
