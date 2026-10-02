<?php

namespace App\Domain\CocIntegration\Data;

/**
 * A league (player league or clan war league). Icon URLs are kept verbatim for GameAssetResolver,
 * which decides whether they may be shown (specs/18 §2.3).
 */
final readonly class LeagueData
{
    /**
     * @param  array<string, string>  $iconUrls  size name → URL
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public array $iconUrls,
    ) {}
}
