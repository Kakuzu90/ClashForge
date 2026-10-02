<?php

namespace App\Domain\CocIntegration\Data;

/**
 * A hero, troop, spell or piece of hero equipment as the API reports it. Names are kept verbatim,
 * including ones we do not know yet (specs/09 §8).
 */
final readonly class UnitData
{
    public function __construct(
        public string $name,
        public int $level,
        public ?int $maxLevel,
        public ?string $village,
        public bool $superTroopIsActive = false,
    ) {}
}
