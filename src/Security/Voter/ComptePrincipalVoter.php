<?php

namespace App\Security\Voter;

use App\Droits\ProfilActif;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Réserve la gestion du compte (paramètres, sous-comptes, relations) au compte principal.
 *
 * @extends Voter<string, null>
 */
final class ComptePrincipalVoter extends Voter
{
    public const COMPTE_PRINCIPAL = 'COMPTE_PRINCIPAL';

    public function __construct(
        private readonly ProfilActif $profilActif,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::COMPTE_PRINCIPAL === $attribute;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        return $user instanceof User && $this->profilActif->pour($user)->estPrincipal();
    }
}
