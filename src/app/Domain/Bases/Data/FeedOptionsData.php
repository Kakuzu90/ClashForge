<?php

namespace App\Domain\Bases\Data;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Bases\Enums\FeedSort;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What a feed's controls offer: the Town Hall range, the categories, the sorts the page allows and
 * the suggested tags (FR-BASE-4, FR-BASE-13).
 */
#[TypeScript]
class FeedOptionsData extends Data
{
    /**
     * @param  list<FeedOptionData>  $categories
     * @param  list<FeedOptionData>  $sorts
     * @param  list<string>  $suggestedTags
     */
    public function __construct(
        public int $thMin,
        public int $thMax,
        public array $categories,
        public array $sorts,
        public array $suggestedTags,
    ) {}

    /**
     * @param  list<FeedSort>  $sorts
     */
    public static function for(array $sorts): self
    {
        /** @var list<string> $tags */
        $tags = array_map(fn (string $tag): string => BaseFieldRules::normaliseTag($tag), (array) config('bases.suggested_tags'));

        return new self(
            thMin: (int) config('bases.th_min'),
            thMax: (int) config('bases.th_max'),
            categories: array_map(fn (BaseCategory $c): FeedOptionData => new FeedOptionData($c->value, $c->label()), BaseCategory::cases()),
            sorts: array_map(fn (FeedSort $s): FeedOptionData => new FeedOptionData($s->value, $s->label()), $sorts),
            suggestedTags: $tags,
        );
    }
}
