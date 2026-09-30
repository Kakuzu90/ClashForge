<?php

namespace App\Domain\GameAssets\Services;

use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Whether game assets may be shown at all, and which pack is live (specs/18 §2.1 (7), specs/24 A22).
 */
class GameAssetPolicy
{
    // D: `$` must not match before a trailing newline.
    private const VERSION_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/D';

    public function enabled(): bool
    {
        return (bool) config('assets.enabled');
    }

    public function activeVersion(): ?string
    {
        if (! $this->versionConfigured()) {
            return null;
        }

        $version = (string) config('assets.pack_version');

        if (! self::isValidVersion($version)) {
            // A typo must not look like "no pack yet": it hides every asset and the integrity check.
            Log::warning('assets.pack_version_invalid', ['value' => $version]);

            return null;
        }

        return $version;
    }

    public function versionConfigured(): bool
    {
        $version = config('assets.pack_version');

        return is_scalar($version) && (string) $version !== '';
    }

    /**
     * Versions end up in bucket keys and URLs, so they are a short, path-safe token.
     */
    public static function isValidVersion(string $version): bool
    {
        return (bool) preg_match(self::VERSION_PATTERN, $version);
    }

    public function manifestPath(string $version): string
    {
        if (! self::isValidVersion($version)) {
            throw new InvalidArgumentException("Invalid pack version [{$version}].");
        }

        return str_replace('{version}', $version, (string) config('assets.manifest_path'));
    }

    /**
     * Bucket prefix for one pack version, with a trailing slash.
     */
    public function prefix(string $version): string
    {
        if (! self::isValidVersion($version)) {
            throw new InvalidArgumentException("Invalid pack version [{$version}].");
        }

        return trim((string) config('assets.prefix'), '/').'/'.$version.'/';
    }
}
