<?php

namespace App\Repository;

use App\Entity\Look;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Look>
 */
class LookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Look::class);
    }

    /**
     * @return list<Look> du plus récent au plus ancien
     */
    public function deProprietaire(User $proprietaire): array
    {
        // Les publications sont chargées avec les looks (badge Instagram de la galerie)
        return $this->createQueryBuilder('l')
            ->leftJoin('l.publications', 'p')
            ->addSelect('p')
            ->andWhere('l.proprietaire = :proprietaire')
            ->setParameter('proprietaire', $proprietaire)
            ->orderBy('l.creeLe', 'DESC')
            ->addOrderBy('l.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
