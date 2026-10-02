<?php

namespace App\Domain\PlayerAccounts\Enums;

use App\Support\Enums\Concerns\EnumHelpers;
use App\Support\Enums\Contracts\HasLabelAndColor;

/**
 * What one account sync did (specs/09 §6).
 */
enum SyncOutcome: string implements HasLabelAndColor
{
    use EnumHelpers;

    // Fresh data stored; `Changed` also wrote a snapshot.
    case Changed = 'changed';
    case Unchanged = 'unchanged';
    // The API no longer finds the tag (a failure for the account).
    case NotFound = 'not_found';
    // The API failed (5xx, timeout, malformed): a failure for the account, retried with backoff.
    case Failed = 'failed';
    // Not the account's fault (circuit open, throttled, no healthy key): retried later, not counted.
    case Postponed = 'postponed';
    // No longer synced in the background (unverified, suspended, released or gone).
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Changed => 'Updated, progress saved',
            self::Unchanged => 'Updated',
            self::NotFound => 'Tag not found',
            self::Failed => 'API failed',
            self::Postponed => 'Postponed',
            self::Skipped => 'Not synced',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Changed, self::Unchanged => 'state-success',
            self::NotFound, self::Failed => 'state-danger',
            self::Postponed => 'state-warning',
            self::Skipped => 'text-muted',
        };
    }
}
