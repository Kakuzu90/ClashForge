<?php

namespace App\Domain\Media\Support;

use finfo;

/**
 * Byte-level checks that run before any decoder sees the file (specs/10 §3 c, §4).
 */
final class FileInspector
{
    /**
     * Markers that have no business inside an image and suggest a polyglot or a script disguised
     * as one. Matched case-insensitively.
     */
    private const SCRIPT_MARKERS = ['<?php', '<script', '<html', '<svg', '<iframe'];

    // Zip end-of-central-directory record; a zip appended to an image ends with it.
    private const ZIP_EOCD = "PK\x05\x06";

    // The EOCD sits in the last 22 bytes plus an optional comment of up to 64 KiB.
    private const ZIP_TAIL_BYTES = 65_557;

    private const CHUNK_BYTES = 1_048_576;

    /**
     * The real MIME type from the file signature, ignoring name and declared type.
     */
    public static function mimeType(string $path): string
    {
        return (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);
    }

    public static function isAnimated(string $path, string $mimeType): bool
    {
        return match ($mimeType) {
            'image/webp' => self::isAnimatedWebp($path),
            'image/png' => self::isAnimatedPng($path),
            'image/gif' => true,
            default => false,
        };
    }

    /**
     * Returns the first reason the file looks like more than an image, or null.
     */
    public static function suspiciousContent(string $path): ?string
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return null;
        }

        try {
            $overlap = max(array_map('strlen', self::SCRIPT_MARKERS)) - 1;
            $carry = '';

            while (! feof($handle)) {
                $chunk = fread($handle, self::CHUNK_BYTES);

                if ($chunk === false || $chunk === '') {
                    break;
                }

                $window = strtolower($carry.$chunk);

                foreach (self::SCRIPT_MARKERS as $marker) {
                    if (str_contains($window, $marker)) {
                        return "script marker {$marker}";
                    }
                }

                $carry = substr($chunk, -$overlap);
            }

            $size = fstat($handle)['size'] ?? 0;
            fseek($handle, max(0, $size - self::ZIP_TAIL_BYTES));
            $tail = (string) stream_get_contents($handle);

            return str_contains($tail, self::ZIP_EOCD) ? 'zip archive appended' : null;
        } finally {
            fclose($handle);
        }
    }

    /**
     * Extended WebP (VP8X) carries an animation flag in its first flags byte.
     */
    private static function isAnimatedWebp(string $path): bool
    {
        $head = (string) file_get_contents($path, false, null, 0, 21);

        return strlen($head) === 21
            && substr($head, 12, 4) === 'VP8X'
            && (ord($head[20]) & 0x02) === 0x02;
    }

    /**
     * APNG declares an acTL chunk before the first IDAT.
     */
    private static function isAnimatedPng(string $path): bool
    {
        $handle = fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        try {
            fseek($handle, 8);

            while (($header = fread($handle, 8)) !== false && strlen($header) === 8) {
                $length = unpack('N', substr($header, 0, 4))[1] ?? 0;
                $type = substr($header, 4, 4);

                if ($type === 'acTL') {
                    return true;
                }

                if ($type === 'IDAT' || $type === 'IEND') {
                    return false;
                }

                fseek($handle, $length + 4, SEEK_CUR);
            }

            return false;
        } finally {
            fclose($handle);
        }
    }
}
