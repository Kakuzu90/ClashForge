<?php

namespace App\Domain\Users\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The owner's own profile, as the settings form edits it.
 */
#[TypeScript]
class ProfileFormData extends Data
{
    /**
     * @param  list<string>  $languages
     */
    public function __construct(
        public string $username,
        public ?string $displayName,
        public ?string $bio,
        public ?string $countryCode,
        public array $languages,
        public ?string $timezone,
        public SocialHandlesData $socials,
        public AvatarData $avatar,
    ) {}
}
