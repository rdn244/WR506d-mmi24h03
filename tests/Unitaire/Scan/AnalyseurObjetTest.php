<?php

namespace App\Tests\Unitaire\Scan;

use App\Photo\PhotoBase64;
use App\Scan\AnalyseException;
use App\Scan\AnalyseurObjet;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

/**
 * Chaîne de secours d'OpenRouter, sans réseau ni attente réelle (horloge simulée).
 */
class AnalyseurObjetTest extends TestCase
{
    private const MODELES = 'modele-a:free,modele-b:free';

    public function testSansCleLAnalyseNEstPasDisponible(): void
    {
        self::assertFalse($this->analyseur(new MockHttpClient(), cle: '')->estConfigure());
        self::assertTrue($this->analyseur(new MockHttpClient())->estConfigure());
    }

    public function testLaListeDeModelesPeutVenirDeLEnvironnement(): void
    {
        $modeles = [];
        $client = new MockHttpClient(static function (string $methode, string $url, array $options) use (&$modeles) {
            $modeles[] = json_decode($options['body'], true)['model'];

            return new MockResponse('{"error": {"code": 429, "message": "quota"}}', ['http_code' => 429]);
        });

        try {
            $this->analyseur($client, modeles: ' modele-a:free , ,modele-b:free ')->analyser($this->photo());
        } catch (AnalyseException) {
        }

        // Liste nettoyée (espaces, entrées vides), essayée deux fois (second tour)
        self::assertSame(['modele-a:free', 'modele-b:free', 'modele-a:free', 'modele-b:free'], $modeles);
    }

    public function testUneCleRefuseeArreteToutDeSuite(): void
    {
        $appels = 0;
        $client = new MockHttpClient(static function () use (&$appels) {
            ++$appels;

            return new MockResponse('{"error": {"code": 401, "message": "No auth credentials found"}}', ['http_code' => 401]);
        });

        try {
            $this->analyseur($client)->analyser($this->photo());
            self::fail('Une clé refusée aurait dû arrêter l\'analyse.');
        } catch (AnalyseException $e) {
            self::assertStringContainsString('clé OpenRouter est refusée', $e->getMessage());
        }
        // Inutile d'essayer les autres modèles : ils refuseraient la même clé
        self::assertSame(1, $appels);
    }

    public function testUnModeleInjoignableFaitPasserAuSuivant(): void
    {
        $reponses = [
            new MockResponse('', ['error' => 'Connexion refusée']),
            self::reponse(['content' => '{"nom": "Pull", "categorie": "Hauts"}']),
        ];

        $resultat = $this->analyseur(new MockHttpClient($reponses))->analyser($this->photo());

        self::assertSame('Pull', $resultat->nom);
        self::assertSame('modele-b:free', $resultat->modele);
    }

    public function testUnContenuEnMorceauxEstRecolle(): void
    {
        $client = new MockHttpClient(self::reponse(['content' => [
            ['type' => 'text', 'text' => '{"nom": "Jean", '],
            ['type' => 'text', 'text' => '"type": "Jean"}'],
            'morceau inattendu',
        ]]));

        self::assertSame('Jean', $this->analyseur($client)->analyser($this->photo())->type);
    }

    public function testUnContenuQuiNEstPasDuTexteEstInexploitable(): void
    {
        $client = new MockHttpClient([self::reponse(['content' => 42]), self::reponse(['content' => '{"nom": "Robe"}'])]);

        self::assertSame('modele-b:free', $this->analyseur($client)->analyser($this->photo())->modele);
    }

    public function testLeBudgetDeTempsArreteLaChaine(): void
    {
        $horloge = new MockClock();
        $appels = 0;
        // Le premier modèle « répond » au bout de 45 s : il ne reste plus le délai minimal pour le suivant
        $client = new MockHttpClient(static function () use ($horloge, &$appels) {
            ++$appels;
            $horloge->sleep(45);

            return new MockResponse('{"error": {"code": 502, "message": "lent"}}', ['http_code' => 502]);
        });

        try {
            $this->analyseur($client, horloge: $horloge)->analyser($this->photo());
            self::fail('L\'analyse aurait dû échouer.');
        } catch (AnalyseException $e) {
            self::assertStringContainsString('saturés', $e->getMessage());
        }
        self::assertSame(1, $appels);
    }

    public function testLaPauseEntreLesToursUtiliseLHorloge(): void
    {
        $horloge = new MockClock('2026-10-14 10:00:00');
        $client = new MockHttpClient([
            new MockResponse('{"error": {"code": 429}}', ['http_code' => 429]),
            new MockResponse('{"error": {"code": 429}}', ['http_code' => 429]),
            self::reponse(['content' => '{"nom": "Sac"}']),
        ]);

        $resultat = $this->analyseur($client, horloge: $horloge)->analyser($this->photo());

        self::assertSame('Sac', $resultat->nom);
        // Une seule pause d'une seconde, simulée : aucun temps réel perdu
        self::assertSame('2026-10-14 10:00:01', $horloge->now()->format('Y-m-d H:i:s'));
    }

    private function analyseur(MockHttpClient $client, string $cle = 'sk-or-test', string $modeles = self::MODELES, ?MockClock $horloge = null): AnalyseurObjet
    {
        return new AnalyseurObjet($client, new NullLogger(), $cle, $modeles, $horloge ?? new MockClock());
    }

    /**
     * @param array<string, mixed> $message
     */
    private static function reponse(array $message): MockResponse
    {
        return new MockResponse((string) json_encode(['choices' => [['message' => $message]]]));
    }

    private function photo(): PhotoBase64
    {
        return PhotoBase64::depuisDataUrl('data:image/jpeg;base64,'.base64_encode((string) file_get_contents(__DIR__.'/../../fixtures/look-portrait.jpg')));
    }
}
