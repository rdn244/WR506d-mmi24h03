<?php

namespace App\Repository;

use App\Entity\Categorie;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categorie>
 */
class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    /**
     * Catégories prédéfinies (dans leur ordre) puis celles du membre (par nom).
     *
     * @return list<Categorie>
     */
    public function disponiblesPour(User $proprietaire): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('CASE WHEN c.proprietaire IS NULL THEN 0 ELSE 1 END AS HIDDEN perso')
            ->where('c.proprietaire IS NULL OR c.proprietaire = :proprietaire')
            ->setParameter('proprietaire', $proprietaire)
            ->orderBy('perso', 'ASC')
            ->addOrderBy('c.ordre', 'ASC')
            ->addOrderBy('c.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Categorie>
     */
    public function persoDe(User $proprietaire): array
    {
        return $this->findBy(['proprietaire' => $proprietaire], ['nom' => 'ASC']);
    }

    /**
     * Le nom est-il déjà pris (catégorie prédéfinie ou du membre, sans tenir compte de la casse) ?
     */
    public function nomPris(User $proprietaire, string $nom, ?Categorie $sauf = null): bool
    {
        $requete = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.proprietaire IS NULL OR c.proprietaire = :proprietaire')
            ->andWhere('LOWER(c.nom) = LOWER(:nom)')
            ->setParameter('proprietaire', $proprietaire)
            ->setParameter('nom', $nom);

        if (null !== $sauf?->getId()) {
            $requete->andWhere('c.id != :sauf')->setParameter('sauf', $sauf->getId());
        }

        return (int) $requete->getQuery()->getSingleScalarResult() > 0;
    }
}
