<?php

namespace App\Domain\Users\Listeners;

use App\Domain\Auth\Services\UserLookupService;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Users\Services\CacheInvalidator;

/**
 * A new avatar finishes processing after the profile was saved, so the cached profile would show
 * the old one (or none) until its TTL. Cache invalidation only, so it runs in the worker that
 * fired the event.
 */
class ForgetProfileWhenAvatarReady
{
    public function __construct(private readonly UserLookupService $users) {}

    public function handle(MediaReady $event): void
    {
        if ($event->collection !== MediaCollection::Avatar) {
            return;
        }

        $username = $this->users->usernameOf($event->userId);

        if ($username !== null) {
            CacheInvalidator::profile($username);
        }
    }
}
