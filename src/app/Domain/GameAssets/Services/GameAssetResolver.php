<?php

namespace App\Domain\GameAssets\Services;

use App\Domain\GameAssets\Data\GameAssetData;
use App\Domain\GameAssets\Enums\GameAssetKind;
use App\Domain\GameAssets\Enums\Village;
use App\Domain\GameAssets\Exceptions\InvalidManifest;
use App\Domain\GameAssets\Support\ManifestEntry;
use App\Domain\GameAssets\Support\PackManifest;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The one place a Clash of Clans asset URL is produced (specs/18 §2.3). Catalogue assets come from
 * the committed manifest of the active pack; clan badges (and leagues missing from the pack) pass
 * through the API's own URLs. Anything it cannot vouch for resolves to our placeholder.
 */
class GameAssetResolver
{
    private ?PackManifest $manifest = null;

    private bool $loaded = false;

    public function __construct(private readonly GameAssetPolicy $policy) {}

    /**
     * Unknown units still resolve, to a placeholder with their name (specs/09 §8 rule 2).
     */
    public function unit(string $name, Village $village = Village::Home): GameAssetData
    {
        $entry = $this->manifest()?->find('unit', $name, $village);

        return $this->make(GameAssetKind::Unit, $entry, $entry->displayName ?? $name, self::initials($name));
    }

    /**
     * The API name of the unit whose pack file is `{slug}` in that village ("lassi" → "L.A.S.S.I"),
     * for catalogue units an account has not unlocked; null when the pack has no such file.
     */
    public function unitName(string $slug, Village $village = Village::Home): ?string
    {
        foreach ($this->manifest()?->entries() ?? [] as $key => $entry) {
            if ($entry->category->isUnit() && $entry->village === $village && pathinfo($key, PATHINFO_FILENAME) === $slug) {
                return $entry->ref;
            }
        }

        return null;
    }

    /**
     * A Town Hall level, or with `Village::Builder` a Builder Hall level.
     */
    public function townHall(int $level, Village $village = Village::Home): GameAssetData
    {
        $entry = $this->manifest()?->find('town_hall', (string) $level, $village);
        $alt = $village === Village::Builder ? "Builder Hall {$level}" : "Town Hall {$level}";

        return $this->make(GameAssetKind::TownHall, $entry, $alt, (string) $level);
    }

    /**
     * Our self-hosted emblem when the pack has it, the API's icon otherwise (specs/09 §8). The pack
     * holds one emblem per league id, or one per family shared by its tiers ("Titan League I" →
     * `titan`); a family emblem keeps the API's name as alt text, since that carries the tier.
     * Builder Base leagues ("Wood League V") live apart, under `Village::Builder`.
     */
    public function league(?int $id, string $name, ?string $apiIconUrl = null, Village $village = Village::Home): GameAssetData
    {
        $in = $village === Village::Builder ? Village::Builder : null;
        $entry = $id === null ? null : $this->manifest()?->find('league', (string) $id, $in);
        $alt = $entry->displayName ?? $name;
        $entry ??= $this->manifest()?->find('league', self::leagueFamily($name), $in);

        if ($entry !== null || ! $this->policy->enabled()) {
            return $this->make(GameAssetKind::League, $entry, $alt, self::initials($name));
        }

        return $this->remote(GameAssetKind::League, $apiIconUrl, $name, self::initials($name));
    }

    /**
     * A ranked league tier ("Legend II", the API's `leagueTier`): the tier's own icon from the API,
     * as the game shows it, when the host is allowed; our pack emblem for its family otherwise.
     */
    public function leagueTier(?int $id, string $name, ?string $apiIconUrl): GameAssetData
    {
        if ($apiIconUrl !== null && $this->policy->enabled() && self::isAllowedRemote($apiIconUrl)) {
            return $this->remote(GameAssetKind::League, $apiIconUrl, $name, self::initials($name));
        }

        return $this->league($id, $name);
    }

    /**
     * @param  array<string, mixed>  $badgeUrls  the API's badgeUrls (small / medium / large)
     */
    public function clanBadge(array $badgeUrls, string $clanName, string $size = 'medium'): GameAssetData
    {
        $url = in_array($size, (array) config('assets.badge_sizes'), true) ? ($badgeUrls[$size] ?? null) : null;

        return $this->remote(GameAssetKind::ClanBadge, is_string($url) ? $url : null, "{$clanName} clan badge", self::initials($clanName));
    }

    private function make(GameAssetKind $kind, ?ManifestEntry $entry, string $alt, string $short): GameAssetData
    {
        $version = $this->policy->activeVersion();

        if ($entry === null || $version === null || ! $this->policy->enabled()) {
            return new GameAssetData($kind, null, $alt, $short, null, null);
        }

        return new GameAssetData($kind, $this->packUrl($version, $entry), $alt, $short, $entry->width, $entry->height);
    }

    private function remote(GameAssetKind $kind, ?string $url, string $alt, string $short): GameAssetData
    {
        $allowed = $url !== null && $this->policy->enabled() && self::isAllowedRemote($url);

        return new GameAssetData($kind, $allowed ? $url : null, $alt, $short, null, null);
    }

    private function packUrl(string $version, ManifestEntry $entry): string
    {
        $path = $this->policy->prefix($version).$entry->key;
        $cdn = config('assets.cdn_url');

        if (is_string($cdn) && $cdn !== '') {
            return rtrim($cdn, '/').'/'.$path;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk((string) config('assets.disk'));

        return $disk->url($path);
    }

    /**
     * API-supplied URLs are rendered only from allowlisted https hosts.
     */
    public static function isAllowedRemote(string $url): bool
    {
        $parts = parse_url($url);

        return is_array($parts)
            && ($parts['scheme'] ?? null) === 'https'
            && ! isset($parts['user'])
            && ! isset($parts['port'])
            && in_array(strtolower($parts['host'] ?? ''), (array) config('assets.remote_hosts'), true);
    }

    /**
     * "P.E.K.K.A League 20" → `pekka`, "Titan League I" → `titan`, "Legend League" → `legend`.
     */
    private static function leagueFamily(string $name): string
    {
        $slug = (string) preg_replace('/-(?:\d+|[ivxlcdm]+)$/', '', Str::slug($name));

        return (string) preg_replace('/-league$/', '', $slug);
    }

    private static function initials(string $name): string
    {
        $words = preg_split('/[\s._-]+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = array_map(fn (string $word): string => mb_substr($word, 0, 1), array_slice($words, 0, 2));

        return mb_strtoupper(implode('', $letters)) ?: '?';
    }

    /**
     * Loaded once per resolver. A missing or broken manifest degrades to placeholders; it never
     * takes a page down.
     */
    private function manifest(): ?PackManifest
    {
        if ($this->loaded) {
            return $this->manifest;
        }

        $this->loaded = true;
        $version = $this->policy->activeVersion();

        if ($version === null || ! $this->policy->enabled()) {
            return null;
        }

        try {
            $manifest = PackManifest::fromFile($this->policy->manifestPath($version));
            $this->manifest = $manifest->version === $version ? $manifest : null;
        } catch (InvalidManifest $e) {
            Log::error('assets.manifest_unreadable', ['version' => $version, 'problems' => $e->problems]);
        }

        return $this->manifest;
    }
}
