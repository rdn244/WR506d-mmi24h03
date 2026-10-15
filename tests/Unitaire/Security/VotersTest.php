<?php

namespace App\Tests\Unitaire\Security;

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
use App\Security\Voter\ComptePrincipalVoter;
use App\Security\Voter\DroitsVoter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\NullToken;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\Voter\VoterInterface;

/**
 * Les voters, porte d'entrée des droits dans les contrôleurs (#[IsGranted]) et les templates (is_granted).
 */
class VotersTest extends TestCase
{
    private User $camille;
    private Session $session;
    private ProfilActif $profilActif;

    protected function setUp(): void
    {
        $this->camille = (new User())->setEmail('camille@dressing.test');
        $this->session = new Session(new MockArraySessionStorage());
        $requete = new Request();
        $requete->setSession($this->session);
        $pile = new RequestStack();
        $pile->push($requete);

        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->camille);
        $this->profilActif = new ProfilActif($security, $pile, $this->createStub(SousCompteRepository::class));
    }

    /**
     * @return iterable<string, array{string, bool, int}>
     */
    public static function votesSurSonLogement(): iterable
    {
        yield 'la propriétaire voit son logement' => [DroitsVoter::VOIR, true, VoterInterface::ACCESS_GRANTED];
        yield 'la propriétaire le modifie' => [DroitsVoter::MODIFIER, true, VoterInterface::ACCESS_GRANTED];
        yield 'un inconnu ne le voit pas' => [DroitsVoter::VOIR, false, VoterInterface::ACCESS_DENIED];
        yield 'un attribut étranger n\'est pas traité' => ['ROLE_ADMIN', true, VoterInterface::ACCESS_ABSTAIN];
    }

    #[DataProvider('votesSurSonLogement')]
    public function testDroitsVoter(string $attribut, bool $proprietaire, int $attendu): void
    {
        $logement = new Logement();
        ($proprietaire ? $this->camille : new User())->addLogement($logement);
        $voter = new DroitsVoter(new ControleAcces(new ResolveurRelation($this->createStub(LienRepository::class))), $this->profilActif);

        self::assertSame($attendu, $voter->vote($this->jeton(), $logement, [$attribut]));
    }

    public function testLeVoterIgnoreUnSujetQuiNEstPasUneRessource(): void
    {
        $voter = new DroitsVoter(new ControleAcces(new ResolveurRelation($this->createStub(LienRepository::class))), $this->profilActif);

        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->jeton(), new \stdClass(), [DroitsVoter::VOIR]));
    }

    public function testSeulLeCompteprincipalGereLeCompte(): void
    {
        $voter = new ComptePrincipalVoter($this->profilActif);

        self::assertSame(VoterInterface::ACCESS_GRANTED, $voter->vote($this->jeton(), null, [ComptePrincipalVoter::COMPTE_PRINCIPAL]));
        self::assertSame(VoterInterface::ACCESS_DENIED, $voter->vote(new NullToken(), null, [ComptePrincipalVoter::COMPTE_PRINCIPAL]));
        self::assertSame(VoterInterface::ACCESS_ABSTAIN, $voter->vote($this->jeton(), null, [DroitsVoter::VOIR]));
    }

    public function testActiverUnSousCompteRetireLesDroitsDuComptePrincipal(): void
    {
        $emma = (new SousCompte())->setDateNaissance(new \DateTimeImmutable('-9 years'));
        // Identifiant attribué par la base en vrai : la session ne garde que lui
        (new \ReflectionProperty(SousCompte::class, 'id'))->setValue($emma, 12);
        $this->camille->addSousCompte($emma);
        $sousComptes = $this->createStub(SousCompteRepository::class);
        $sousComptes->method('find')->willReturn($emma);
        $security = $this->createStub(Security::class);
        $security->method('getUser')->willReturn($this->camille);
        $pile = new RequestStack();
        $requete = new Request();
        $requete->setSession($this->session);
        $pile->push($requete);
        $profil = new ProfilActif($security, $pile, $sousComptes);

        $profil->activer($emma);
        self::assertTrue($this->session->has('profil_actif'));
        self::assertEquals(new Acteur($this->camille, $emma), $profil->acteur());
        self::assertSame(VoterInterface::ACCESS_DENIED, (new ComptePrincipalVoter($profil))->vote($this->jeton(), null, [ComptePrincipalVoter::COMPTE_PRINCIPAL]));

        $profil->activer(null);
        self::assertFalse($this->session->has('profil_actif'));
        self::assertTrue($profil->acteur()?->estPrincipal());
    }

    public function testLeLibelleDeChaqueRelation(): void
    {
        $libelles = array_map(static fn (Relation $relation) => $relation->libelle(), Relation::cases());

        self::assertSame(['Propriétaire', 'Sous-compte +18', 'Sous-compte enfant', 'Compte lié', 'Ami', 'Abonné', 'Membre', 'Bloqué'], $libelles);
    }

    public function testLIdentiteAfficheeDeLActeur(): void
    {
        $camille = (new User())->setPrenom('camille');
        $emma = (new SousCompte())->setPrenom('Emma')->setDateNaissance(new \DateTimeImmutable('-9 years'));

        self::assertSame('camille', (new Acteur($camille))->getPrenom());
        self::assertSame('C', (new Acteur($camille))->getInitiale());
        self::assertSame('Compte principal', (new Acteur($camille))->getLibelle());
        self::assertSame('Emma', (new Acteur($camille, $emma))->getPrenom());
        self::assertSame('E', (new Acteur($camille, $emma))->getInitiale());
        self::assertSame('Sous-compte enfant', (new Acteur($camille, $emma))->getLibelle());
    }

    private function jeton(): UsernamePasswordToken
    {
        return new UsernamePasswordToken($this->camille, 'principal', ['ROLE_USER']);
    }
}
