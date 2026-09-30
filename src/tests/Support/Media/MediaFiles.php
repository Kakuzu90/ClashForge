<?php

namespace Tests\Support\Media;

use GdImage;
use RuntimeException;
use ZipArchive;

/**
 * Builds image bytes for pipeline tests, including the hostile shapes from specs/11 §4 (upload
 * suite). Generated in memory so the fixtures stay readable and need no binary files.
 */
final class MediaFiles
{
    public static function jpeg(int $width = 1200, int $height = 900): string
    {
        return self::render(self::canvas($width, $height), fn (GdImage $image) => imagejpeg($image, null, 90));
    }

    public static function png(int $width = 1200, int $height = 900): string
    {
        return self::render(self::canvas($width, $height), fn (GdImage $image) => imagepng($image));
    }

    public static function webp(int $width = 1200, int $height = 900): string
    {
        return self::render(self::canvas($width, $height), fn (GdImage $image) => imagewebp($image, null, 90));
    }

    /**
     * A JPEG carrying an EXIF block with a GPS IFD (GPSLatitudeRef = N).
     */
    public static function jpegWithGps(int $width = 1200, int $height = 900): string
    {
        // Little-endian TIFF: IFD0 has one entry pointing at the GPS IFD, which has one ASCII tag.
        $tiff = 'II'.pack('v', 42).pack('V', 8)
            .pack('v', 1).pack('vvVV', 0x8825, 4, 1, 26).pack('V', 0)
            .pack('v', 1).pack('vvV', 0x0001, 2, 2)."N\0\0\0".pack('V', 0);
        $payload = "Exif\0\0".$tiff;
        $app1 = "\xFF\xE1".pack('n', strlen($payload) + 2).$payload;

        $jpeg = self::jpeg($width, $height);

        return substr($jpeg, 0, 2).$app1.substr($jpeg, 2);
    }

    /**
     * An extended WebP header with the animation flag set; enough for signature and flag checks.
     */
    public static function animatedWebp(): string
    {
        $vp8x = 'VP8X'.pack('V', 10).chr(0x02).str_repeat("\0", 3)."\xFF\x01\x00"."\xFF\x01\x00";
        $anim = 'ANIM'.pack('V', 6).str_repeat("\0", 6);
        $body = 'WEBP'.$vp8x.$anim;

        return 'RIFF'.pack('V', strlen($body)).$body;
    }

    /**
     * A real PNG with an acTL chunk inserted after IHDR, which makes it an APNG.
     */
    public static function apng(int $width = 400, int $height = 400): string
    {
        $png = self::png($width, $height);
        $ihdrEnd = 8 + 8 + 13 + 4;

        return substr($png, 0, $ihdrEnd).self::chunk('acTL', pack('NN', 2, 0)).substr($png, $ihdrEnd);
    }

    /**
     * Signature + IHDR + IEND and no pixel data: a header that claims any canvas size.
     */
    public static function pngHeaderOnly(int $width, int $height): string
    {
        return "\x89PNG\r\n\x1a\n"
            .self::chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .self::chunk('IEND', '');
    }

    public static function withAppendedZip(string $image): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zip');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::OVERWRITE);
        $zip->addFromString('payload.txt', 'not an image');
        $zip->close();

        $bytes = (string) file_get_contents($path);
        unlink($path);

        return $image.$bytes;
    }

    private static function canvas(int $width, int $height): GdImage
    {
        $image = imagecreatetruecolor($width, $height);

        if ($image === false) {
            throw new RuntimeException('GD could not allocate the test canvas.');
        }

        imagefilledrectangle($image, 0, 0, $width - 1, $height - 1, (int) imagecolorallocate($image, 40, 60, 120));
        imagefilledellipse($image, intdiv($width, 2), intdiv($height, 2), intdiv($width, 2), intdiv($height, 2), (int) imagecolorallocate($image, 240, 190, 40));

        return $image;
    }

    /**
     * @param  callable(GdImage): mixed  $encode
     */
    private static function render(GdImage $image, callable $encode): string
    {
        ob_start();
        $encode($image);

        return (string) ob_get_clean();
    }

    private static function chunk(string $type, string $data): string
    {
        return pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));
    }
}
