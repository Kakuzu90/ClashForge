<?php

namespace App\Http\Data\Profile;

use App\Domain\Users\Data\PublicProfileData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Props for Profile/Show.
 */
#[TypeScript]
class ProfileShowPageData extends Data
{
    public function __construct(
        public PublicProfileData $profile,
    ) {}
}
