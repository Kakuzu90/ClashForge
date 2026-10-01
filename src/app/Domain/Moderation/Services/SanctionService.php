<?php

namespace App\Domain\Moderation\Services;

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Data\AuditEntryData;
use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Enums\AuditSubject;
use App\Domain\Audit\Services\AuditLogger;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Services\UserStatusService;
use App\Domain\Moderation\Data\ApplySanctionData;
use App\Domain\Moderation\Data\SanctionAbilitiesData;
use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Enums\SanctionType;
use App\Domain\Moderation\Events\SanctionApplied;
use App\Domain\Moderation\Events\SanctionLifted;
use App\Domain\Moderation\Exceptions\SanctionRefused;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\Users\Services\CacheInvalidator;
use App\Models\User;
use App\Support\Privacy\IpHash;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Suspensions and bans (specs/12 §6, FR-ADMIN-3). Each one writes `moderation_actions`,
 * `user_sanctions` and `audit_logs`, and sets the account's status through Auth, in one
 * transaction with the account row locked, so it applies on the account's next request.
 *
 * An account has at most one active suspension or ban (owner decision, P1-14): a new suspension
 * or a ban replaces an active suspension, and a banned account cannot be suspended or banned
 * again. Reasons and notes are required here, not only in the form (specs/04 rule 2).
 */
class SanctionService
{
    public function __construct(
        private readonly UserStatusService $statuses,
        private readonly AuditLogger $audit,
        private readonly Request $request,
    ) {}

    public function suspend(User $actor, User $target, ApplySanctionData $data): UserSanction
    {
        Gate::forUser($actor)->authorize('suspend', $target);

        $max = (int) config('moderation.sanctions.suspension_max_days');
        if ($data->days === null || $data->days < 1 || $data->days > $max) {
            throw new InvalidArgumentException("A suspension runs 1 to {$max} days.");
        }

        return $this->apply($actor, $target, SanctionType::Suspension, $data);
    }

    public function ban(User $actor, User $target, ApplySanctionData $data): UserSanction
    {
        Gate::forUser($actor)->authorize('ban', $target);

        // A ban has no length, whatever the caller passed: the record is permanent.
        $data = new ApplySanctionData($data->reasonCode, $data->publicReason, $data->internalNote, days: null);

        return $this->apply($actor, $target, SanctionType::Ban, $data);
    }

    /**
     * Ends the account's active suspension or ban; `note` says why (specs/12 §6).
     */
    public function lift(User $actor, User $target, string $note): UserSanction
    {
        Gate::forUser($actor)->authorize('liftSanction', $target);
        $this->requireNote($note);

        [$sanction, $username] = DB::transaction(function () use ($actor, $target, $note): array {
            $account = $this->lock($target);
            // Again on the locked row: the role may have changed since the check above.
            Gate::forUser($actor)->authorize('liftSanction', $account);
            $sanction = $this->activeSanction($account);

            if ($sanction === null) {
                throw SanctionRefused::because('This account has no active suspension or ban to lift.');
            }

            $before = $this->statusOf($account);
            $this->close($sanction, $actor, $note);
            $this->statuses->clearSanction($account);

            $this->audit->record(new AuditEntryData(
                actor: new AuditActorData(id: $actor->id, role: $actor->role->value),
                action: AuditAction::SanctionLifted,
                subject: AuditSubject::User,
                subjectId: $account->id,
                before: $before,
                after: $this->statusOf($account),
                context: ['sanction' => $sanction->type->value, 'note' => $note],
            ));

            return [$sanction, $account->username];
        });

        $this->securityLog('moderation.sanction_lifted', $actor, $target, ['type' => $sanction->type->value]);
        CacheInvalidator::profile($username);
        SanctionLifted::dispatch($target->id, $sanction->id, false);

        return $sanction;
    }

    /**
     * Ends every suspension past its end (specs/20: every 15 minutes). The status check per
     * request already treats them as ended (specs/23 §7); this clears the columns and tells the
     * account holder. Safe to run twice: an account is only picked while its status is still set.
     */
    public function expireDue(): int
    {
        $due = User::query()->withTrashed()
            ->whereIn('status', [UserStatus::Suspended->value, UserStatus::Restricted->value])
            ->whereNotNull('status_expires_at')
            ->where('status_expires_at', '<=', Date::now())
            ->orderBy('id')
            ->pluck('id');

        $count = 0;
        foreach ($due as $userId) {
            $ended = DB::transaction(function () use ($userId): ?array {
                $account = User::query()->withTrashed()->lockForUpdate()->whereKey($userId)->first();

                if ($account === null || $account->status_expires_at === null || $account->status_expires_at->greaterThan(Date::now())
                    || ! in_array($account->status, [UserStatus::Suspended, UserStatus::Restricted], true)) {
                    return null;
                }

                $sanction = UserSanction::query()->where('user_id', $account->id)->whereNull('lifted_at')
                    ->whereNotNull('expires_at')->orderByDesc('id')->first();
                $before = $this->statusOf($account);
                $this->statuses->clearSanction($account);

                $this->audit->record(new AuditEntryData(
                    actor: AuditActorData::scheduler(),
                    action: AuditAction::SanctionExpired,
                    subject: AuditSubject::User,
                    subjectId: $account->id,
                    before: $before,
                    after: $this->statusOf($account),
                    context: ['sanction' => $sanction?->type->value],
                ));

                return [$account, $sanction];
            });

            if ($ended === null) {
                continue;
            }

            [$account, $sanction] = $ended;
            $count++;
            Log::channel('security')->info('moderation.sanction_expired', ['user' => $account->ulid]);
            CacheInvalidator::profile($account->username);

            if ($sanction !== null) {
                SanctionLifted::dispatch($account->id, $sanction->id, true);
            }
        }

        return $count;
    }

    /**
     * What the actor may do to this account now, for the action panel (show/hide only).
     */
    public function abilities(User $actor, User $target): SanctionAbilitiesData
    {
        $active = $this->activeSanction($target);
        $sanctionable = $this->refusal($target) === null;
        $gate = Gate::forUser($actor);

        return new SanctionAbilitiesData(
            suspend: $sanctionable && $gate->allows('suspend', $target),
            ban: $sanctionable && $gate->allows('ban', $target),
            lift: $active !== null && $gate->allows('liftSanction', $target),
            activeType: $active?->type->value,
        );
    }

    private function apply(User $actor, User $target, SanctionType $type, ApplySanctionData $data): UserSanction
    {
        $this->requireNote($data->internalNote);
        $reason = trim($data->publicReason);
        $max = (int) config('moderation.sanctions.public_reason_max');
        if ($reason === '' || mb_strlen($reason) > $max) {
            throw new InvalidArgumentException("The message to the account holder is required, at most {$max} characters.");
        }

        $now = Date::now()->toImmutable();
        $until = $type === SanctionType::Suspension ? $now->addDays((int) $data->days) : null;

        [$sanction, $username] = DB::transaction(function () use ($actor, $target, $type, $data, $reason, $now, $until): array {
            $account = $this->lock($target);
            // Again on the locked row: the role may have changed since the check above.
            Gate::forUser($actor)->authorize($type === SanctionType::Ban ? 'ban' : 'suspend', $account);

            $refusal = $this->refusal($account);
            if ($refusal !== null) {
                throw SanctionRefused::because($refusal);
            }

            $before = $this->statusOf($account);
            $replaced = $this->activeSanction($account);
            if ($replaced !== null) {
                $this->close($replaced, $actor, 'Replaced by a new '.strtolower($type->label()).'.');
            }

            $action = ModerationAction::query()->create([
                'actor_id' => $actor->id,
                'action' => $type === SanctionType::Ban ? ModerationActionType::Ban : ModerationActionType::Suspend,
                'target_type' => ModerationAction::TARGET_USER,
                'target_id' => $account->id,
                'target_user_id' => $account->id,
                'reason_code' => $data->reasonCode,
                'note' => trim($data->internalNote),
                'duration_hours' => $data->days === null ? null : $data->days * 24,
                'metadata' => $replaced === null ? [] : ['replaced_sanction_id' => $replaced->id],
                'ip_hash' => $this->ipHash(),
            ]);

            $sanction = UserSanction::query()->create([
                'user_id' => $account->id,
                'type' => $type,
                'reason_code' => $data->reasonCode,
                'public_reason' => $reason,
                'internal_note' => trim($data->internalNote),
                'issued_by' => $actor->id,
                'starts_at' => $now,
                'expires_at' => $until,
                'moderation_action_id' => $action->id,
            ]);

            $this->statuses->applySanction($account, $type === SanctionType::Ban ? UserStatus::Banned : UserStatus::Suspended, $reason, $until);

            $this->audit->record(new AuditEntryData(
                actor: new AuditActorData(id: $actor->id, role: $actor->role->value),
                action: AuditAction::SanctionApplied,
                subject: AuditSubject::User,
                subjectId: $account->id,
                before: $before,
                after: $this->statusOf($account),
                context: array_filter([
                    'sanction' => $type->value,
                    'reason_code' => $data->reasonCode->value,
                    'days' => $data->days,
                    'replaced' => $replaced === null ? null : $replaced->type->value,
                ], fn (mixed $value): bool => $value !== null),
            ));

            return [$sanction, $account->username];
        });

        $this->securityLog('moderation.sanction_applied', $actor, $target, ['type' => $type->value, 'until' => $until?->toIso8601String()]);
        CacheInvalidator::profile($username);
        SanctionApplied::dispatch($target->id, $sanction->id);

        return $sanction;
    }

    /**
     * Why this account cannot take a new suspension or ban, or null when it can.
     */
    private function refusal(User $account): ?string
    {
        return match (true) {
            $account->deleted_at !== null => 'This account is deleted.',
            $account->effectiveStatus() === UserStatus::Banned => 'This account is already banned. Lift the ban first.',
            $account->effectiveStatus() === UserStatus::PendingDeletion => 'This account is waiting for deletion.',
            default => null,
        };
    }

    private function activeSanction(User $account): ?UserSanction
    {
        return UserSanction::query()->active()
            ->where('user_id', $account->id)
            ->whereIn('type', [SanctionType::Suspension->value, SanctionType::Ban->value])
            ->orderByDesc('id')
            ->first();
    }

    private function close(UserSanction $sanction, User $actor, string $note): void
    {
        $sanction->forceFill(['lifted_by' => $actor->id, 'lifted_at' => Date::now()])->save();

        ModerationAction::query()->create([
            'actor_id' => $actor->id,
            'action' => $sanction->type === SanctionType::Ban ? ModerationActionType::Unban : ModerationActionType::Lift,
            'target_type' => ModerationAction::TARGET_SANCTION,
            'target_id' => $sanction->id,
            'target_user_id' => $sanction->user_id,
            'reason_code' => $sanction->reason_code,
            'note' => trim($note),
            'ip_hash' => $this->ipHash(),
        ]);
    }

    private function lock(User $target): User
    {
        return User::query()->withTrashed()->lockForUpdate()->findOrFail($target->id);
    }

    /**
     * @return array<string, string|null>
     */
    private function statusOf(User $account): array
    {
        return [
            'status' => $account->status->value,
            'reason' => $account->status_reason,
            'until' => $account->status_expires_at instanceof CarbonImmutable ? $account->status_expires_at->toIso8601String() : null,
        ];
    }

    private function requireNote(string $note): void
    {
        $max = (int) config('moderation.sanctions.note_max');
        $note = trim($note);

        if ($note === '' || mb_strlen($note) > $max) {
            throw new InvalidArgumentException("An internal note is required, at most {$max} characters.");
        }
    }

    private function ipHash(): ?string
    {
        return $this->request->route() !== null ? IpHash::of($this->request->ip()) : null;
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function securityLog(string $message, User $actor, User $target, array $extra): void
    {
        Log::channel('security')->warning($message, ['actor' => $actor->ulid, 'user' => $target->ulid, ...$extra, 'ip_hash' => $this->ipHash()]);
    }
}
