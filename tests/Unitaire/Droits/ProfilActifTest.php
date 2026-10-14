<?php

namespace App\Tests\Unitaire\Droits;

use App\Droits\Acteur;
use App\Droits\ControleAcces;
use App\Droits\ProfilActif;
use App\Droits\Relation;
use App\Droits\ResolveurRelation;
use App\Entity\Logement;
use App\Entity\SousCompte;
use App\Entity\User;
use App\Repository\LienRepository;
use App\Repository\SousCompteRepository;
use App\Security\Voter\DroitsVoter;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Profil actif en session et cas limites des droits (sans base de données).
 */
class ProfilActifTest extends TestCase
{
    public function testSansUtilisateurConnecteIlNYAPasDActeur(): void
    {
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn(null);

        self::assertNull((new ProfilActif($security, new RequestStack(), $this->createStub(SousCompteRepository::class)))->acteur());
    }

    public function testUnSousCompteDUnAutreCompteEstRetireDeLaSession(): void
    {
        $camille = new User();
        $emmaDAlex = (new SousCompte())->setComptePrincipal(new User());
        $sousComptes = $this->createStub(SousCompteRepository::class);
        $sousComptes->method('find')->willReturn($emmaDAlex);

        $session = new Session(new MockArraySessionStorage());
        $session->set('profil_actif', 12);
        $requete = new Request();
        $requete->setSession($session);
        $pile = new RequestStack();
        $pile->push($requete);

        $acteur = (new ProfilActif($this->createStub(Security::class), $pile, $sousComptes))->pour($camille);

        self::assertTrue($acteur->estPrincipal());
        self::assertFalse($session->has('profil_actif'));
    }

    public function testUnSousCompteNADeDroitsQueChezSonComptePrincipal(): void
    {
        $emma = (new SousCompte())->setComptePrincipal(new User())->setDateNaissance(new \DateTimeImmutable('-20 years'));
        $resolveur = new ResolveurRelation($this->createStub(LienRepository::class));

        self::assertSame(Relation::Inconnu, $resolveur->relation(new Acteur(new User(), $emma), new User()));
    }

    public function testLeVoterRefuseUnJetonSansUtilisateur(): void
    {
        $voter = new DroitsVoter(
            new ControleAcces(new ResolveurRelation($this->createStub(LienRepository::class))),
            new ProfilActif($this->createStub(Security::class), new RequestStack(), $this->createStub(SousCompteRepository::class)),
        );

        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), new Logement(), [DroitsVoter::VOIR]));
    }
}
