<?php

namespace App\Droits;

use App\Entity\Lien;
use App\Entity\User;
use App\Enum\TypeLien;
use App\Repository\LienRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Règles de création et de suppression des liens entre membres.
 * Les erreurs métier sont levées en \DomainException avec un message affichable.
 */
final class GestionLiens
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly LienRepository $liens,
    ) {
    }

    /**
     * Demande d'ami ou invitation de compte lié. Si l'autre membre avait déjà fait la demande, elle est acceptée.
     */
    public function demander(TypeLien $type, User $demandeur, User $destinataire): Lien
    {
        \assert(\in_array($type, [TypeLien::Ami, TypeLien::CompteLie], true));
        $this->verifierPossible($demandeur, $destinataire);

        $existant = $this->liens->entre($type, $demandeur, $destinataire);
        if (null !== $existant) {
            if ($existant->estAccepte()) {
                throw new \DomainException(TypeLien::Ami === $type ? 'Vous êtes déjà amis.' : 'Vos comptes sont déjà liés.');
            }
            if ($existant->getDemandeur() === $demandeur) {
                throw new \DomainException('Ta demande est déjà envoyée.');
            }
            $existant->accepter();
            $this->em->flush();

            return $existant;
        }

        $lien = new Lien($type, $demandeur, $destinataire);
        $this->em->persist($lien);
        $this->em->flush();

        return $lien;
    }

    public function accepter(TypeLien $type, User $destinataire, User $demandeur): void
    {
        $lien = $this->liens->trouver($type, $demandeur, $destinataire);
        if (null === $lien || $lien->estAccepte()) {
            throw new \DomainException('Cette demande n\'existe plus.');
        }

        $lien->accepter();
        $this->em->flush();
    }

    /**
     * Refuse, annule ou retire un lien d'ami ou de compte lié, quel que soit son sens.
     */
    public function retirer(TypeLien $type, User $a, User $b): void
    {
        $lien = $this->liens->entre($type, $a, $b);
        if (null !== $lien) {
            $this->em->remove($lien);
            $this->em->flush();
        }
    }

    public function sAbonner(User $abonne, User $suivi): void
    {
        $this->verifierPossible($abonne, $suivi);

        if (null === $this->liens->trouver(TypeLien::Abonnement, $abonne, $suivi)) {
            $this->em->persist(new Lien(TypeLien::Abonnement, $abonne, $suivi));
            $this->em->flush();
        }
    }

    public function seDesabonner(User $abonne, User $suivi): void
    {
        $lien = $this->liens->trouver(TypeLien::Abonnement, $abonne, $suivi);
        if (null !== $lien) {
            $this->em->remove($lien);
            $this->em->flush();
        }
    }

    /**
     * Bloquer supprime aussi toute amitié, liaison de comptes et abonnement entre les deux membres.
     */
    public function bloquer(User $bloqueur, User $bloque): void
    {
        if ($bloqueur === $bloque) {
            throw new \DomainException('Tu ne peux pas te bloquer toi-même.');
        }

        foreach ([TypeLien::Ami, TypeLien::CompteLie, TypeLien::Abonnement] as $type) {
            foreach ([[$bloqueur, $bloque], [$bloque, $bloqueur]] as [$de, $vers]) {
                $lien = $this->liens->trouver($type, $de, $vers);
                if (null !== $lien) {
                    $this->em->remove($lien);
                }
            }
        }

        if (null === $this->liens->trouver(TypeLien::Blocage, $bloqueur, $bloque)) {
            $this->em->persist(new Lien(TypeLien::Blocage, $bloqueur, $bloque));
        }
        $this->em->flush();
    }

    public function debloquer(User $bloqueur, User $bloque): void
    {
        $lien = $this->liens->trouver(TypeLien::Blocage, $bloqueur, $bloque);
        if (null !== $lien) {
            $this->em->remove($lien);
            $this->em->flush();
        }
    }

    private function verifierPossible(User $de, User $vers): void
    {
        if ($de === $vers) {
            throw new \DomainException('Cette action n\'est pas possible avec ton propre compte.');
        }
        if (null !== $this->liens->entre(TypeLien::Blocage, $de, $vers)) {
            throw new \DomainException('Action impossible : l\'un de vous a bloqué l\'autre.');
        }
    }
}
