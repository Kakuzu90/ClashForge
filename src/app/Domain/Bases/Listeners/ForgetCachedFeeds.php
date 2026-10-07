<?php

namespace App\Domain\Bases\Listeners;

use App\Domain\Auth\Events\AccountDeletionRequested;
use App\Domain\Bases\Events\BasePublished;
use App\Domain\Bases\Services\CacheInvalidator;
use App\Domain\Moderation\Events\SanctionApplied;
use App\Domain\Moderation\Events\SanctionLifted;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountReleased;
use App\Domain\Users\Events\PrivacySettingsChanged;
use Illuminate\Events\Dispatcher;

/**
 * Drops every cached feed page (specs/21 §4): a new base must show, and a suspended or banned
 * author's bases must leave the feeds at once rather than after the cache TTL (specs/21 §4
 * "Rule"). A lifted sanction brings them back the same way; a deletion request hides the author's
 * bases; a credited account changing hands, or the author's privacy settings, change what a card
 * shows of them.
 */
class ForgetCachedFeeds
{
    public function __construct(private readonly CacheInvalidator $caches) {}

    public function handle(): void
    {
        $this->caches->baseFeeds();
    }

    /**
     * @return array<class-string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        return [
            BasePublished::class => 'handle',
            SanctionApplied::class => 'handle',
            SanctionLifted::class => 'handle',
            CocAccountReleased::class => 'handle',
            CocAccountOwnershipTransferred::class => 'handle',
            PrivacySettingsChanged::class => 'handle',
            AccountDeletionRequested::class => 'handle',
        ];
    }
}
