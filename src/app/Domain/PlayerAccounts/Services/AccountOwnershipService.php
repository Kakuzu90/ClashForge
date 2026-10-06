<?php

namespace App\Domain\PlayerAccounts\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Services\PasswordConfirmationService;
use App\Domain\Auth\Services\UserStatusService;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\ReleaseReason;
use App\Domain\PlayerAccounts\Events\CocAccountReleased;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Support\AccountImages;
use App\Domain\PlayerAccounts\Support\FeaturedAccount;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use SensitiveParameter;

/**
 * What an owner does with their own rows (specs/13 §6, FR-COC-12/13): detach one, which releases
 * the tag to anyone with a token, and choose the featured one. Another user's ulid is a 404.
 */
class AccountOwnershipService
{
    public function __construct(
        private readonly UserStatusService $users,
        private readonly PasswordConfirmationService $passwords,
        private readonly AuditLogger $audit,
        private readonly AccountImages $images,
    ) {}

    /**
     * Detach after the password is typed again (specs/13 §6). The row keeps its snapshots and is
     * reused by whoever attaches the tag next. Returns the released tag.
     */
    public function detach(User $user, string $accountUlid, #[SensitiveParameter] string $currentPassword, ?string $ip): string
    {
        $account = $this->own($user, $accountUlid);
        Gate::forUser($user)->authorize('detach', $account);

        return DB::transaction(function () use ($user, $account, $currentPassword, $ip): string {
            // The order verification locks in: every row of the tag, then the user.
            $row = CocAccount::query()->where('tag_normalized', $account->tag_normalized)->orderBy('id')->lockForUpdate()->get()
                ->firstWhere('id', $account->id);
            $this->users->lockAccounts([$user->id]);
            $locked = User::query()->findOrFail($user->id);

            // Again on the locked row and account: a supersede or a sanction may have come in since.
            if ($row === null || $row->user_id !== $user->id) {
                throw (new ModelNotFoundException)->setModel(CocAccount::class, [$account->ulid]);
            }
            Gate::forUser($locked)->authorize('detach', $row);
            $this->passwords->confirm($locked, $currentPassword, $ip);

            $this->release($row, $locked, ReleaseReason::Detach, new AuditActorData(id: $locked->id, role: $locked->role->value));

            return $row->tag;
        });
    }

    /**
     * Run inside the caller's transaction with the tag's rows and the user locked. Clears the owner
     * and the featured flag, moves the flag to another account, closes the user's pending claims,
     * recounts and audits, and deletes the account's images (P2-23). P2-24 releases a deleted or
     * banned user's tags through here.
     */
    public function release(CocAccount $row, User $owner, ReleaseReason $reason, AuditActorData $actor): void
    {
        $before = $row->status;
        $this->images->releaseAll($row);
        $row->forceFill([
            'user_id' => null,
            'status' => CocAccountStatus::Released,
            'verified_at' => null,
            'verification_method' => null,
            'is_featured' => false,
        ])->save();

        // This user's claims only: a reused row also carries earlier users' history.
        CocAccountClaim::query()->where('coc_account_id', $row->id)->where('user_id', $owner->id)
            ->where('status', ClaimStatus::Pending)->update(['status' => ClaimStatus::Superseded]);

        FeaturedAccount::fallback($owner->id);
        $this->users->syncVerifiedAccounts(
            $owner->id,
            CocAccount::query()->where('user_id', $owner->id)->whereIn('status', CocAccountStatus::HOLDING)->count(),
        );

        $this->audit->record(new AuditEntryData(
            actor: $actor,
            action: AuditAction::CocAccountReleased,
            subject: AuditSubject::CocAccount,
            subjectId: $row->id,
            before: ['status' => $before->value, 'user_id' => $owner->id],
            after: ['status' => CocAccountStatus::Released->value, 'user_id' => null],
            context: ['tag' => $row->tag, 'reason' => $reason->value],
        ));

        CocAccountReleased::dispatch($row->id, $owner->id, $reason);
    }

    /**
     * Makes this row the user's featured account (FR-COC-12).
     */
    public function feature(User $user, string $accountUlid): void
    {
        $account = $this->own($user, $accountUlid);
        Gate::forUser($user)->authorize('feature', $account);

        DB::transaction(function () use ($user, $account): void {
            // This row and the current featured one, in id order, then the user: rows before users,
            // as verification locks.
            $rows = CocAccount::query()->where('user_id', $user->id)
                ->where(fn ($query) => $query->whereKey($account->id)->orWhere('is_featured', true))
                ->orderBy('id')->lockForUpdate()->get();
            $this->users->lockAccounts([$user->id]);
            $locked = User::query()->findOrFail($user->id);

            $row = $rows->firstWhere('id', $account->id) ?? throw (new ModelNotFoundException)->setModel(CocAccount::class, [$account->ulid]);
            Gate::forUser($locked)->authorize('feature', $row);

            if ($row->is_featured) {
                return;
            }
            // A row featured since the select (a fallback elsewhere) is locked here after the user:
            // the one wait in the other order, so a deadlock is retried rather than shown as an error.
            CocAccount::query()->where('user_id', $user->id)->where('is_featured', true)->update(['is_featured' => false]);
            $row->forceFill(['is_featured' => true])->save();
        }, attempts: 3);
    }

    private function own(User $user, string $ulid): CocAccount
    {
        return CocAccount::query()->where('ulid', $ulid)->where('user_id', $user->id)->firstOrFail();
    }
}
