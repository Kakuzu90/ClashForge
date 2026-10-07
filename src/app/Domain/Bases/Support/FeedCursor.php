<?php

namespace App\Domain\Bases\Support;

use App\Domain\Bases\Data\FeedFiltersData;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * A keyset position in one feed (specs/17 §4): the last card's sort value and id, the page number
 * that follows, and the feed it belongs to. Encrypted with the app key, so a visitor can neither
 * read the internal id, forge a position, nor reset the page count that caps anonymous paging; a
 * cursor from another feed or a tampered one is refused.
 */
final readonly class FeedCursor
{
    private function __construct(
        public string $value,
        public int $id,
        public int $page,
    ) {}

    public static function for(FeedFiltersData $filters, string $value, int $id, int $page): string
    {
        return Crypt::encryptString(json_encode(['v' => $value, 'i' => $id, 'p' => $page, 'f' => self::feed($filters)], JSON_THROW_ON_ERROR));
    }

    public static function decode(string $cursor, FeedFiltersData $filters): ?self
    {
        try {
            $data = json_decode(Crypt::decryptString($cursor), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || ($data['f'] ?? null) !== self::feed($filters)
            || ! is_string($data['v'] ?? null) || ! is_int($data['i'] ?? null) || ! is_int($data['p'] ?? null) || $data['i'] < 1 || $data['p'] < 2) {
            return null;
        }

        return new self($data['v'], $data['i'], $data['p']);
    }

    private static function feed(FeedFiltersData $filters): string
    {
        return substr(hash('sha256', $filters->signature()), 0, 16);
    }
}
