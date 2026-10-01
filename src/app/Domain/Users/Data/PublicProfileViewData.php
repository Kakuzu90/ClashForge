<?php

namespace App\Domain\Users\Data;

/**
 * A viewable public profile plus what the page head needs but the props must not carry: whether
 * search engines may index it (`public` and `searchable`, specs/17 §6).
 */
final readonly class PublicProfileViewData
{
    public function __construct(
        public PublicProfileData $profile,
        public bool $indexable,
    ) {}
}
