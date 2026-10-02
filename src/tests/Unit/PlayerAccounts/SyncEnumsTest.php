<?php

use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use App\Domain\PlayerAccounts\Enums\SnapshotSource;

// The values are the specs/07 CHECK lists of `sync_states` and `coc_account_snapshots`; a new case
// needs a migration.

it('matches the specs/07 value lists', function (string $enum, array $values) {
    expect($enum::values())->toBe($values);
})->with([
    [SyncResourceType::class, ['coc_account', 'clan']],
    [SyncTier::class, ['hot', 'warm', 'cold', 'frozen']],
    [SnapshotSource::class, ['scheduled', 'manual', 'verification']],
]);
