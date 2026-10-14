<?php

namespace App\Repository;

use App\Entity\Objet;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Objet>
 */
class ObjetRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Objet::class);
    }

    /**
     * @return list<Objet> du plus récent au plus ancien, avec leur rangement
     */
    public function deProprietaire(User $proprietaire): array
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.contenant', 'c')
            ->addSelect('c')
            ->leftJoin('o.categorie', 'cat')
            ->addSelect('cat')
            ->andWhere('o.proprietaire = :proprietaire')
            ->setParameter('proprietaire', $proprietaire)
            ->orderBy('o.creeLe', 'DESC')
            ->addOrderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre d'objets du membre par catégorie (identifiant => nombre).
     *
     * @return array<int, int>
     */
    public function comptesParCategorie(User $proprietaire): array
    {
        $lignes = $this->createQueryBuilder('o')
            ->select('IDENTITY(o.categorie) AS categorie', 'COUNT(o.id) AS nombre')
            ->andWhere('o.proprietaire = :proprietaire')
            ->andWhere('o.categorie IS NOT NULL')
            ->setParameter('proprietaire', $proprietaire)
            ->groupBy('o.categorie')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $l) => [(int) $l['categorie'], (int) $l['nombre']], $lignes), 1, 0);
    }
}
