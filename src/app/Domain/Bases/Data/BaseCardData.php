<?php

namespace App\Domain\Bases\Data;

use App\Domain\Bases\Enums\BaseCategory;
use App\Domain\Media\Data\MediaVariantData;
use App\Domain\PlayerAccounts\Data\CreditedAccountData;
use App\Domain\Users\Data\AuthorData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A base in a feed (specs/18 §4 BaseCard). Viewer-independent, so a list of them can be cached; the
 * viewer's own likes and bookmarks join with P3-04. `cover` is the first screenshot's card
 * rendition, else the video poster, else null (the generated fallback). `credit` is null when the
 * account changed hands or its holder keeps their accounts private.
 */
#[TypeScript]
class BaseCardData extends Data
{
    public function __construct(
        public string $ulid,
        public string $slug,
        public string $title,
        public int $thLevel,
        public BaseCategory $category,
        public bool $hasVideo,
        public ?MediaVariantData $cover,
        public int $likes,
        public int $copies,
        public int $views,
        public AuthorData $author,
        public ?CreditedAccountData $credit,
    ) {}
}
