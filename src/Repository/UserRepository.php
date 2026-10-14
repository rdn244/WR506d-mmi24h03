<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Recherche de membres par prénom, nom ou email exact (l'email n'est jamais deviné partiellement).
     *
     * @return list<User>
     */
    public function rechercher(string $terme, User $exclu, int $limite = 20): array
    {
        $terme = trim($terme);
        if (mb_strlen($terme) < 2) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u != :exclu')
            ->andWhere('u.email = :email OR u.prenom LIKE :motif OR u.nom LIKE :motif OR CONCAT(u.prenom, \' \', COALESCE(u.nom, \'\')) LIKE :motif')
            ->setParameter('exclu', $exclu)
            ->setParameter('email', mb_strtolower($terme))
            ->setParameter('motif', '%'.addcslashes($terme, '%_').'%')
            ->orderBy('u.prenom', 'ASC')
            ->setMaxResults($limite)
            ->getQuery()
            ->getResult();
    }

    /**
     * Re-hache automatiquement le mot de passe lorsque l'algorithme évolue.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->flush();
    }
}
