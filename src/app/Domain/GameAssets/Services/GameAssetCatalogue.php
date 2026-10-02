<?php

namespace App\Domain\GameAssets\Services;

/**
 * The catalogue order of `config/assets.php` (P2-05) for the progression grids (P2-04). The lists
 * use pack file slugs and the API sends display names, so a name maps to a slug by one rule, with
 * `assets.aliases` for any name the rule gets wrong. Units with no pack image still sort by it.
 */
class GameAssetCatalogue
{
    /**
     * "P.E.K.K.A" → `pekka`, "Healing Spell" → `healing`, "Barbarian King" → `barbarian-king`.
     */
    public function slug(string $name): string
    {
        $aliases = (array) config('assets.aliases');

        if (is_string($aliases[$name] ?? null)) {
            return $aliases[$name];
        }

        $slug = str_replace(['.', "'", '’'], '', mb_strtolower(trim($name)));
        $slug = (string) preg_replace('/ spell$/', '', $slug);

        return (string) preg_replace('/\s+/', '-', $slug);
    }

    /**
     * Slug → position in one catalogue list (`heroes`, `units`, `spells`, `pets`, `siege-machines`,
     * `heroes_equipments`). Nested lists (elixir then dark elixir, equipment per hero) are read in
     * order.
     *
     * @return array<string, int>
     */
    public function positions(string $list): array
    {
        $slugs = [];
        $config = (array) config("assets.{$list}");
        array_walk_recursive($config, function (mixed $slug) use (&$slugs): void {
            if (is_string($slug)) {
                $slugs[] = $slug;
            }
        });

        return array_flip(array_values(array_unique($slugs)));
    }

    /**
     * Units kept out of the grids: super troops (shown apart, while active) and guardians.
     */
    public function excluded(string $name): bool
    {
        return in_array($this->slug($name), (array) config('assets.excluded_units'), true);
    }
}
