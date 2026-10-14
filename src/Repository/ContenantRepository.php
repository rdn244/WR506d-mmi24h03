<?php

namespace App\Repository;

use App\Entity\Contenant;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contenant>
 */
class ContenantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contenant::class);
    }

    /**
     * Rangements partagés d'un membre, tous logements confondus.
     *
     * @return list<Contenant>
     */
    public function partagesDe(User $proprietaire): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.piece', 'p')
            ->join('p.logement', 'l')
            ->where('l.proprietaire = :proprietaire')
            ->andWhere('c.partage = true')
            ->setParameter('proprietaire', $proprietaire)
            ->orderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les rangements d'un membre, avec leur pièce (choix du rangement d'un objet).
     *
     * @return list<Contenant>
     */
    public function deProprietaire(User $proprietaire): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.piece', 'p')
            ->addSelect('p')
            ->join('p.logement', 'l')
            ->where('l.proprietaire = :proprietaire')
            ->setParameter('proprietaire', $proprietaire)
            ->orderBy('p.id', 'ASC')
            ->addOrderBy('c.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
