<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Auth\Contracts\DeletionHold;
use App\Domain\Auth\Contracts\DeletionStep;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeStatus;
use App\Domain\PlayerAccounts\Enums\ReleaseReason;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountDispute;
use App\Models\User;

/**
 * PlayerAccounts' part of account deletion (specs/08 §6, specs/13 §6). A running ownership dispute
 * with the user on either side holds the anonymisation (specs/23 §1). At the end of the window the
 * user's tags are released inside the anonymisation transaction. A `suspended` row stays as staff
 * left it, so deleting the account is no way out of a suspension.
 */
class AccountDeletionHooks implements DeletionHold, DeletionStep
{
    /** Released at the end of the deletion window: everything but a suspension (cf. BannedTagRelease::ON_BAN). */
    private const ON_DELETION = [CocAccountStatus::Unverified, CocAccountStatus::Verified, CocAccountStatus::Disputed];

    public function __construct(private readonly AccountOwnershipService $ownership) {}

    public function reasonFor(int $userId): ?string
    {
        $running = CocAccountDispute::query()
            ->whereIn('status', DisputeStatus::ACTIVE)
            ->where(fn ($query) => $query->where('claimant_id', $userId)->orWhere('current_holder_id', $userId))
            ->exists();

        return $running ? 'You are part of an ownership dispute that is still open. Your account is deleted once it is resolved.' : null;
    }

    public function lock(int $userId): void
    {
        CocAccount::query()->where('user_id', $userId)->orderBy('id')->lockForUpdate()->pluck('id');
    }

    public function run(int $userId): void
    {
        $owner = User::query()->withTrashed()->find($userId);
        if ($owner === null) {
            return;
        }

        $rows = CocAccount::query()->where('user_id', $userId)->whereIn('status', self::ON_DELETION)->orderBy('id')->get();
        foreach ($rows as $row) {
            $this->ownership->release($row, $owner, ReleaseReason::Deletion, AuditActorData::console());
        }
    }
}
