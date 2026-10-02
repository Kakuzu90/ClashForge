<?php

namespace App\Domain\CocIntegration\Data;

final readonly class LocationData
{
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $countryCode,
    ) {}
}
