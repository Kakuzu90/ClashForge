<?php

namespace App\Domain\CocIntegration\Data;

final readonly class LabelData
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
