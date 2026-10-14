<?php

namespace App\Tests\Unitaire\Photo;

use App\Photo\PhotoBase64;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Validation de la photo reçue en base64 : c'est une donnée externe.
 */
class PhotoBase64Test extends TestCase
{
    public function testUnePhotoValideEstDecodeeEtReencodee(): void
    {
        $jpeg = (string) file_get_contents(__DIR__.'/../../fixtures/look-portrait.jpg');
        $photo = PhotoBase64::depuisDataUrl('data:image/jpeg;base64,'.base64_encode($jpeg));

        self::assertSame($jpeg, $photo->binaire);
        self::assertSame('image/jpeg', $photo->typeMime);
        self::assertSame('jpg', $photo->extension);
        self::assertSame('data:image/jpeg;base64,'.base64_encode($jpeg), $photo->versDataUrl());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function photosRefusees(): iterable
    {
        yield 'pas une data URL' => ['https://exemple.test/photo.jpg', 'JPEG, PNG ou WebP'];
        yield 'type non accepté' => ['data:image/gif;base64,R0lGODlh', 'JPEG, PNG ou WebP'];
        yield 'base64 invalide' => ['data:image/png;base64,@@@', 'JPEG, PNG ou WebP'];
        yield 'pas une image' => ['data:image/png;base64,'.base64_encode('<?php echo 1;'), 'illisible'];
        yield 'type annoncé faux' => ['data:image/png;base64,'.base64_encode((string) file_get_contents(__DIR__.'/../../fixtures/look-portrait.jpg')), 'illisible'];
        yield 'trop lourde' => ['data:image/jpeg;base64,'.str_repeat('A', 7 * 1024 * 1024 + 8), 'trop lourde'];
        yield 'trop grande' => ['data:image/png;base64,'.base64_encode(self::pngVide(5000, 2)), 'trop grande'];
    }

    #[DataProvider('photosRefusees')]
    public function testUnePhotoInvalideEstRefusee(string $dataUrl, string $message): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage($message);

        PhotoBase64::depuisDataUrl($dataUrl);
    }

    /**
     * PNG noir aux dimensions demandées (quelques octets une fois compressé).
     */
    private static function pngVide(int $largeur, int $hauteur): string
    {
        $morceau = static function (string $type, string $donnees): string {
            return pack('N', \strlen($donnees)).$type.$donnees.pack('N', crc32($type.$donnees));
        };
        $lignes = str_repeat("\0".str_repeat("\0\0\0", $largeur), $hauteur);

        return "\x89PNG\r\n\x1a\n"
            .$morceau('IHDR', pack('NNCCCCC', $largeur, $hauteur, 8, 2, 0, 0, 0))
            .$morceau('IDAT', (string) gzcompress($lignes))
            .$morceau('IEND', '');
    }
}
