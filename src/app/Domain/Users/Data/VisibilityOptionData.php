<?php

namespace App\Domain\Users\Data;

use App\Domain\Users\Enums\ProfileVisibility;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One choice in the profile visibility radio group.
 */
#[TypeScript]
class VisibilityOptionData extends Data
{
    public function __construct(
        public ProfileVisibility $value,
        public string $label,
        public string $description,
    ) {}

    /**
     * @return list<self>
     */
    public static function choices(): array
    {
        return array_map(
            fn (ProfileVisibility $case): self => new self($case, $case->label(), $case->description()),
            ProfileVisibility::cases(),
        );
    }
}
