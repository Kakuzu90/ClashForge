<?php

namespace App\Domain\CocIntegration\Data;

/**
 * The clan block of a player payload. `role` comes from the player's top-level `role` field.
 */
final readonly class PlayerClanData
{
    /**
     * @param  array<string, string>  $badgeUrls  size name → URL, stored and rendered unmodified
     */
    public function __construct(
        public ClanTag $tag,
        public string $name,
        public ?string $role,
        public ?int $level,
        public array $badgeUrls,
    ) {}
}
