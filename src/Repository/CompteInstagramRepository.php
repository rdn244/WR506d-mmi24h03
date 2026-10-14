<?php

namespace App\Repository;

use App\Entity\CompteInstagram;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CompteInstagram>
 */
class CompteInstagramRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CompteInstagram::class);
    }

    public function deUtilisateur(User $utilisateur): ?CompteInstagram
    {
        return $this->findOneBy(['utilisateur' => $utilisateur]);
    }
}
