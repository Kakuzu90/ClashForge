<?php

namespace App\Domain\Bases\Support;

use App\Support\ValueObjects\StringValueObject;

/**
 * An official in-game base link (FR-BASE-2): `https://link.clashofclans.com/{lang}?action=OpenLayout&id=…`.
 * Stored in one canonical form: https, the game's host, the language path (`en` when missing), and
 * only `action` and `id`, so tracking parameters never reach the page. We cannot read what the
 * layout contains (specs/23 §3), only that the link has the game's shape.
 */
final class BaseLink extends StringValueObject
{
    public const HOST = 'link.clashofclans.com';

    // The game's layout ids look like `TH16:WB:AAAA…`; anything outside this set is not one.
    private const ID_PATTERN = '/^[A-Za-z0-9:_\-+\/=.]{8,256}$/D';

    /**
     * The layout id the link opens, as the game wrote it.
     */
    public function layoutId(): string
    {
        parse_str((string) parse_url($this->value, PHP_URL_QUERY), $query);

        return is_string($query['id'] ?? null) ? $query['id'] : '';
    }

    protected static function normalize(string $value): string
    {
        $value = trim($value);
        $parts = parse_url($value);

        if (! is_array($parts) || strtolower($parts['scheme'] ?? '') !== 'https' || strtolower($parts['host'] ?? '') !== self::HOST) {
            return $value;
        }

        parse_str($parts['query'] ?? '', $query);
        $path = $parts['path'] ?? '';
        $lang = preg_match('/^\/([a-z]{2}(?:-[a-z]{2})?)\/?$/iD', $path, $match) === 1 ? strtolower($match[1]) : 'en';
        $action = is_string($query['action'] ?? null) ? $query['action'] : '';
        $id = is_string($query['id'] ?? null) ? $query['id'] : '';

        return 'https://'.self::HOST."/{$lang}?".http_build_query(['action' => $action, 'id' => $id], encoding_type: PHP_QUERY_RFC3986);
    }

    protected static function validate(string $value): ?string
    {
        if (! str_starts_with($value, 'https://'.self::HOST.'/')) {
            return 'Paste the link from the game: it starts with https://'.self::HOST.'/.';
        }

        parse_str((string) parse_url($value, PHP_URL_QUERY), $query);

        if (($query['action'] ?? null) !== 'OpenLayout') {
            return 'This link does not open a base layout. Copy the link of a base in the game.';
        }

        if (! is_string($query['id'] ?? null) || preg_match(self::ID_PATTERN, $query['id']) !== 1) {
            return 'This base link is incomplete. Copy it again from the game.';
        }

        return null;
    }
}
