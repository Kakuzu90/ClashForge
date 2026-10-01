<?php

namespace App\Domain\Notifications\Data;

use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class EmailPreferencesData extends Data
{
    /** @param list<EmailCategoryData> $categories */
    public function __construct(
        public bool $emailEnabled,
        #[DataCollectionOf(EmailCategoryData::class)]
        public array $categories,
        public bool $canUpdate,
    ) {}
}
