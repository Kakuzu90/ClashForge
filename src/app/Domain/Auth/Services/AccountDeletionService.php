<?php

namespace App\Domain\Auth\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\UsernameHistory;
use App\Domain\Media\Services\MediaLifecycleService;
use App\Domain\Notifications\Services\NotificationCleanupService;
use App\Domain\Users\Services\CacheInvalidator;
use App\Domain\Users\Services\ProfileAnonymisationService;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AccountDeletionService
{
    public function __construct(
        private readonly SessionService $sessions,
        private readonly ProfileAnonymisationService $profiles,
        private readonly NotificationCleanupService $notifications,
        private readonly MediaLifecycleService $media,
        private readonly AuditLogger $audit,
    ) {}

    public function request(User $actor, #[\SensitiveParameter] string $currentPassword): void
    {
        DB::transaction(function () use ($actor, $currentPassword): void {
            $account = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('requestDeletion', $account);

            if ($account->password === null || ! Hash::check($currentPassword, $account->password)) {
                Log::channel('security')->warning('auth.password_confirm_failed', ['user' => $account->ulid, 'ip_hash' => IpHash::of(request()->ip())]);

                throw ValidationException::withMessages(['current_password' => 'That is not your current password.']);
            }

            $previous = $account->effectiveStatus();
            $account->forceFill([
                'status' => UserStatus::PendingDeletion,
                'deletion_requested_at' => Date::now(),
                'deletion_previous_status' => $previous,
                'status_reason' => $previous === UserStatus::Active ? null : $account->status_reason,
                'status_expires_at' => $previous === UserStatus::Active ? null : $account->status_expires_at,
            ])->save();
            $this->sessions->endOthers($account, null);

            DB::afterCommit(fn () => CacheInvalidator::profile($account->username));
        });

        Log::channel('security')->info('auth.deletion_requested', ['user' => $actor->ulid]);
    }

    public function anonymiseDue(bool $dryRun = false): int
    {
        $due = $this->due();
        if ($dryRun) {
            return $due->count();
        }

        $count = 0;
        $due->select('id')->chunkById((int) config('platform.auth.deletion_batch_size'), function ($accounts) use (&$count): void {
            foreach ($accounts as $account) {
                $count += (int) $this->anonymise($account->id);
            }
        });

        return $count;
    }

    public function anonymise(int $userId): bool
    {
        return DB::transaction(function () use ($userId): bool {
            // Re-check after locking: a sign-in may have cancelled since the batch selected it.
            $account = $this->due()->whereKey($userId)->lockForUpdate()->first();
            if ($account === null) {
                return false;
            }

            UsernameHistory::query()->create([
                'user_id' => $account->id, 'username' => $account->username,
                'released_at' => Date::now(), 'reserved_forever' => true,
            ]);
            $this->profiles->anonymise($account);
            $this->notifications->deleteFor($account);
            $this->media->purgeOwnedBy($account->id);
            DB::table('password_reset_tokens')->where('email', $account->email)->delete();
            DB::table((string) config('session.table', 'sessions'))->where('user_id', $account->id)->delete();

            $account->forceFill([
                'username' => 'deleted_user_'.strtolower($account->ulid),
                'email' => hash_hmac('sha256', $account->ulid.':'.strtolower($account->email), (string) config('app.key')),
                'password' => null, 'remember_token' => null, 'email_verified_at' => null,
                'pending_email' => null, 'pending_email_requested_at' => null,
                'last_login_at' => null, 'last_login_ip_hash' => null,
                'deletion_previous_status' => null, 'status' => UserStatus::Banned,
                'status_reason' => null, 'status_expires_at' => null, 'deleted_at' => Date::now(),
            ])->save();

            $this->audit->record(new AuditEntryData(
                actor: AuditActorData::console(), action: AuditAction::UserAnonymised,
                subject: AuditSubject::User, subjectId: $account->id,
                before: ['status' => UserStatus::PendingDeletion->value],
                after: ['status' => UserStatus::Banned->value],
                context: ['command' => 'platform:anonymize-deleted'],
            ));

            return true;
        });
    }

    /** @return Builder<User> */
    private function due(): Builder
    {
        return User::query()->where('status', UserStatus::PendingDeletion)
            ->where('deletion_requested_at', '<=', Date::now()->subDays((int) config('platform.auth.deletion_grace_days')));
    }
}
