<?php

namespace App\Domain\Bases\Data;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\BaseVisibility;

/**
 * What the author submits to publish a base (FR-BASE-1). Media are upload ULIDs from the
 * `base_screenshot` and `base_video` collections; `accountUlid` null credits the featured account.
 */
final readonly class PublishBaseData
{
    /**
     * @param  list<string>  $tags  as typed; normalised by TagName
     * @param  list<string>  $screenshots
     */
    public function __construct(
        public string $title,
        public ?string $description,
        public int $thLevel,
        public BaseCategory $category,
        public string $baseLink,
        public BaseVisibility $visibility,
        public array $tags = [],
        public array $screenshots = [],
        public ?string $video = null,
        public ?string $accountUlid = null,
    ) {}
}
