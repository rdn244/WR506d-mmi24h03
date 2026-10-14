<?php

namespace App\Tests\Unitaire\Enum;

use App\Enum\FormatPublication;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Formats acceptés par Instagram : le feed refuse les photos trop étirées, la story accepte tout.
 */
class FormatPublicationTest extends TestCase
{
    /**
     * @return iterable<string, array{FormatPublication, int, int, bool}>
     */
    public static function dimensions(): iterable
    {
        yield 'feed portrait 4:5' => [FormatPublication::Feed, 1080, 1350, true];
        yield 'feed limite 9:16' => [FormatPublication::Feed, 900, 1600, true];
        yield 'feed limite 1,91:1' => [FormatPublication::Feed, 1910, 1000, true];
        yield 'feed trop haut' => [FormatPublication::Feed, 500, 1000, false];
        yield 'feed trop large' => [FormatPublication::Feed, 3000, 1000, false];
        yield 'feed dimensions illisibles' => [FormatPublication::Feed, 0, 0, false];
        yield 'story panoramique' => [FormatPublication::Story, 3000, 1000, true];
    }

    #[DataProvider('dimensions')]
    public function testAccepteDimensions(FormatPublication $format, int $largeur, int $hauteur, bool $accepte): void
    {
        self::assertSame($accepte, $format->accepteDimensions($largeur, $hauteur));
    }

    public function testLibellesEtIcones(): void
    {
        self::assertSame(['Feed', 'Story'], array_map(static fn (FormatPublication $f) => $f->libelle(), FormatPublication::cases()));
        self::assertSame(['ph:device-mobile-duotone', 'ph:timer-duotone'], array_map(static fn (FormatPublication $f) => $f->icone(), FormatPublication::cases()));
    }
}
