<?php

namespace App\Domain\Users\Support;

use InvalidArgumentException;

/**
 * Social handles, never URLs (specs/07 `profiles.socials`). Links are built from fixed https
 * hosts on render, so nothing a user typed ever reaches an href. Discord has no public profile
 * URL and is shown as text.
 */
final readonly class SocialLinks
{
    /**
     * Handle rules per network, and the profile URL each one opens (null: shown as text).
     */
    private const NETWORKS = [
        // YouTube handles: 3–30 of letters, digits, `_`, `-`, `.`, stored with the leading @.
        'youtube' => ['pattern' => '/^@[A-Za-z0-9_.\-]{3,30}$/', 'url' => 'https://www.youtube.com/', 'hint' => 'Use your YouTube handle, like @clashchief.'],
        'twitch' => ['pattern' => '/^[A-Za-z0-9_]{4,25}$/', 'url' => 'https://www.twitch.tv/', 'hint' => 'Use your Twitch username, 4 to 25 letters, numbers or underscores.'],
        'x' => ['pattern' => '/^[A-Za-z0-9_]{1,15}$/', 'url' => 'https://x.com/', 'hint' => 'Use your X handle without the @, up to 15 letters, numbers or underscores.'],
        'discord' => ['pattern' => '/^[a-z0-9_.]{2,32}$/', 'url' => null, 'hint' => 'Use your Discord username, 2 to 32 lowercase letters, numbers, dots or underscores.'],
    ];

    /**
     * @param  array<string, string>  $handles
     */
    private function __construct(public array $handles) {}

    /**
     * @return list<string>
     */
    public static function networks(): array
    {
        return array_keys(self::NETWORKS);
    }

    /**
     * @param  array<string, mixed>  $input  network → handle; empty values are dropped
     */
    public static function from(array $input): self
    {
        $handles = [];

        foreach (self::networks() as $network) {
            $handle = self::normalize($network, $input[$network] ?? null);

            if ($handle === null) {
                continue;
            }

            if (($error = self::errorFor($network, $handle)) !== null) {
                throw new InvalidArgumentException($error);
            }

            $handles[$network] = $handle;
        }

        return new self($handles);
    }

    public static function errorFor(string $network, mixed $value): ?string
    {
        $handle = self::normalize($network, $value);

        if ($handle === null) {
            return null;
        }

        $rule = self::NETWORKS[$network] ?? null;

        return $rule !== null && preg_match($rule['pattern'], $handle) === 1 ? null : ($rule['hint'] ?? 'Unknown network.');
    }

    /**
     * @return array<string, array{handle: string, url: string|null}>
     */
    public function links(): array
    {
        $links = [];

        // Handles are limited to URL-safe characters by NETWORKS, so they need no encoding.
        foreach ($this->handles as $network => $handle) {
            $base = self::NETWORKS[$network]['url'];
            $links[$network] = ['handle' => $handle, 'url' => $base === null ? null : $base.$handle];
        }

        return $links;
    }

    private static function normalize(string $network, mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        return match ($network) {
            'youtube' => str_starts_with($value, '@') ? $value : '@'.$value,
            'x' => ltrim($value, '@'),
            'discord' => strtolower($value),
            default => $value,
        };
    }
}
