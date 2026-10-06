<?php

namespace App\Domain\Users\Data;

/**
 * A viewable public profile plus what the page head needs but the props must not carry: whether
 * search engines may index it (`public` and `searchable`, specs/17 §6), and whose profile it is, for
 * the reads other modules add to the page (the accounts, P2-22).
 */
final readonly class PublicProfileViewData
{
    public function __construct(
        public PublicProfileData $profile,
        public bool $indexable,
        public int $ownerId,
    ) {}
}
