<?php

namespace App\Domain\Search\Data;

use Carbon\CarbonImmutable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * A keyset position in one result list (specs/17 §4): the last hit's sort values and id, the page
 * that follows, the time the list was ranked at, and the search it belongs to. Encrypted with the
 * app key like the feed cursor, so it cannot be read, forged, moved to another search or reset to
 * dodge the anonymous page cap.
 */
final readonly class SearchCursor
{
    /**
     * @param  list<string>  $values
     */
    private function __construct(
        public array $values,
        public int $id,
        public int $page,
        public CarbonImmutable $at,
    ) {}

    /**
     * @param  list<string>  $values
     */
    public static function for(string $signature, array $values, int $id, int $page, CarbonImmutable $at): string
    {
        return Crypt::encryptString(json_encode([
            'v' => $values, 'i' => $id, 'p' => $page, 't' => $at->getTimestamp(), 'f' => self::list($signature),
        ], JSON_THROW_ON_ERROR));
    }

    public static function decode(string $cursor, string $signature): ?self
    {
        try {
            $data = json_decode(Crypt::decryptString($cursor), true);
        } catch (DecryptException) {
            return null;
        }

        if (! is_array($data) || ($data['f'] ?? null) !== self::list($signature) || ! is_array($data['v'] ?? null)
            || ! is_int($data['i'] ?? null) || ! is_int($data['p'] ?? null) || ! is_int($data['t'] ?? null) || $data['i'] < 1 || $data['p'] < 2) {
            return null;
        }

        $values = [];
        foreach ($data['v'] as $value) {
            if (! is_string($value)) {
                return null;
            }
            $values[] = $value;
        }

        return new self($values, $data['i'], $data['p'], CarbonImmutable::createFromTimestamp($data['t']));
    }

    private static function list(string $signature): string
    {
        return substr(hash('sha256', $signature), 0, 16);
    }
}
