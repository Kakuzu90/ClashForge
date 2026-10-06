<?php

namespace App\Domain\PlayerAccounts\Events;

use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A holder's answer is due and a reminder day has come (specs/16 §2, day 3 and day 6 of the wait,
 * P2-18). `number` counts the reminders of this wait, so the email is sent once per reminder.
 */
final class CocAccountDisputeReminderDue implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $disputeId,
        public readonly int $number,
        public readonly int $daysLeft,
        /** Unix time the wait began: with `number`, the email's event key */
        public readonly int $awaitingSince,
    ) {}
}
