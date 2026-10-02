<?php

namespace App\Domain\CocIntegration\Data;

use App\Support\ValueObjects\StringValueObject;

/**
 * A player or clan tag in its one canonical form (FR-COC-2): uppercase, one leading `#`, the
 * letter O read as zero, then 3 to 12 characters from the game's tag alphabet. Invalid input never
 * reaches the API.
 */
abstract class CocTag extends StringValueObject
{
    public const ALPHABET = '0289PYLQGRJCUV';

    public const MIN = 3;

    public const MAX = 12;

    /**
     * "player" or "clan", for the messages.
     */
    abstract protected static function noun(): string;

    /**
     * The tag as it goes in a URL path: `%23ABC`.
     */
    public function urlEncoded(): string
    {
        return rawurlencode($this->value);
    }

    /**
     * The tag without its `#`, for file names and cache keys.
     */
    public function bare(): string
    {
        return substr($this->value, 1);
    }

    protected static function normalize(string $value): string
    {
        // strtoupper is ASCII-only, so a look-alike letter from another script stays as typed
        // and fails the alphabet check instead of being folded into a valid tag.
        $value = strtoupper(trim($value));

        if (str_starts_with($value, '#')) {
            $value = substr($value, 1);
        }

        return '#'.str_replace('O', '0', $value);
    }

    protected static function validate(string $value): ?string
    {
        $body = substr($value, 1);
        $noun = static::noun();

        if ($body === '') {
            return "Enter a {$noun} tag.";
        }

        if (preg_match('/^['.self::ALPHABET.']+$/D', $body) !== 1) {
            return "A {$noun} tag uses only these characters: 0 2 8 9 P Y L Q G R J C U V.";
        }

        $length = strlen($body);

        if ($length < self::MIN || $length > self::MAX) {
            return "A {$noun} tag has ".self::MIN.' to '.self::MAX.' characters after the #.';
        }

        return null;
    }
}
