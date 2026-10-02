<?php

namespace App\Domain\CocIntegration\Data;

final readonly class AchievementData
{
    public function __construct(
        public string $name,
        public ?int $stars,
        public ?int $value,
        public ?int $target,
        public ?string $village,
    ) {}
}
