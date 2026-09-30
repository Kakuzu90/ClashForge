<?php

namespace App\Domain\Media\Support;

use App\Support\ValueObjects\StringValueObject;

/**
 * The uploader's filename, kept for display only and never used as a storage key (specs/10 §4).
 */
final class OriginalFilename extends StringValueObject
{
    protected static function normalize(string $value): string
    {
        // Drop any client path, control characters and repeated whitespace.
        $name = basename(str_replace('\\', '/', $value));
        $name = (string) preg_replace('/[\p{C}]+/u', '', $name);
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));

        return mb_substr($name, -self::maxLength());
    }

    protected static function validate(string $value): ?string
    {
        return $value === '' ? 'The filename is empty.' : null;
    }

    public static function maxLength(): int
    {
        return (int) config('media.filename.max_length');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->value, PATHINFO_EXTENSION));
    }
}
