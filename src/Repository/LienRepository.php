<?php

namespace App\Repository;

use App\Entity\Lien;
use App\Entity\User;
use App\Enum\StatutLien;
use App\Enum\TypeLien;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Lien>
 */
class LienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Lien::class);
    }

    /**
     * Lien orienté demandeur → destinataire.
     */
    public function trouver(TypeLien $type, User $demandeur, User $destinataire): ?Lien
    {
        return $this->findOneBy(['type' => $type, 'demandeur' => $demandeur, 'destinataire' => $destinataire]);
    }

    /**
     * Lien dans un sens ou dans l'autre.
     */
    public function entre(TypeLien $type, User $a, User $b): ?Lien
    {
        return $this->trouver($type, $a, $b) ?? $this->trouver($type, $b, $a);
    }

    public function existeAccepteEntre(TypeLien $type, User $a, User $b): bool
    {
        return (bool) $this->entre($type, $a, $b)?->estAccepte();
    }

    /**
     * Liens acceptés impliquant l'utilisateur, dans les deux sens.
     *
     * @return list<Lien>
     */
    public function acceptesDe(TypeLien $type, User $user): array
    {
        return $this->createQueryBuilder('l')
            ->where('l.type = :type')
            ->andWhere('l.statut = :statut')
            ->andWhere('l.demandeur = :user OR l.destinataire = :user')
            ->setParameter('type', $type)
            ->setParameter('statut', StatutLien::Accepte)
            ->setParameter('user', $user)
            ->orderBy('l.creeLe', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Lien>
     */
    public function recus(TypeLien $type, User $user, StatutLien $statut): array
    {
        return $this->findBy(['type' => $type, 'destinataire' => $user, 'statut' => $statut], ['creeLe' => 'ASC']);
    }

    /**
     * @return list<Lien>
     */
    public function envoyes(TypeLien $type, User $user, StatutLien $statut): array
    {
        return $this->findBy(['type' => $type, 'demandeur' => $user, 'statut' => $statut], ['creeLe' => 'ASC']);
    }
}
