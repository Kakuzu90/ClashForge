<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Moderation\Data\ApplySanctionData;
use App\Domain\Moderation\Enums\ModerationActionType;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Enums\SanctionType;
use App\Domain\Moderation\Exceptions\ModerationActionIsImmutable;
use App\Domain\Moderation\Exceptions\SanctionRefused;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\Moderation\Notifications\AccountBannedNotification;
use App\Domain\Moderation\Notifications\AccountSuspendedNotification;
use App\Domain\Moderation\Notifications\SanctionEndedNotification;
use App\Domain\Moderation\Queries\SanctionHistoryQuery;
use App\Domain\Moderation\Services\SanctionService;
use App\Domain\Users\Services\CacheInvalidator;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Auth\CapturesSecurityLog;

// specs/12 §6, FR-ADMIN-3, FR-MOD-5/6/8: every sanction writes moderation_actions,
// user_sanctions and audit_logs and sets the status in one transaction; one active suspension or
// ban per account (owner decision, P1-14).

uses(CapturesSecurityLog::class);

beforeEach(function () {
    $this->captureSecurityLog();
    Notification::fake();
    Date::setTestNow('2026-10-01 12:00:00');
    $this->admin = User::factory()->admin()->create(['username' => 'warden']);
    $this->target = User::factory()->create(['username' => 'chief']);
    $this->sanctions = app(SanctionService::class);
});

function sanctionInput(?int $days = 7, string $reason = 'Harassment in comments', string $note = 'Three reports, two warnings.'): ApplySanctionData
{
    return new ApplySanctionData(reasonCode: ReasonCode::Harassment, publicReason: $reason, internalNote: $note, days: $days);
}

it('suspends: action, sanction, status, audit entry, security line and email', function () {
    $sanction = $this->sanctions->suspend($this->admin, $this->target, sanctionInput(7));

    $this->target->refresh();
    $action = ModerationAction::query()->sole();
    $audit = AuditLog::query()->sole();

    expect($this->target->status)->toBe(UserStatus::Suspended)
        ->and($this->target->status_reason)->toBe('Harassment in comments')
        ->and($this->target->status_expires_at->toIso8601String())->toBe('2026-10-08T12:00:00+00:00')
        ->and($sanction->type)->toBe(SanctionType::Suspension)
        ->and($sanction->issued_by)->toBe($this->admin->id)
        ->and($sanction->internal_note)->toBe('Three reports, two warnings.')
        ->and($sanction->moderation_action_id)->toBe($action->id)
        ->and($action->action)->toBe(ModerationActionType::Suspend)
        ->and($action->target_type)->toBe(ModerationAction::TARGET_USER)
        ->and($action->target_user_id)->toBe($this->target->id)
        ->and($action->duration_hours)->toBe(168)
        ->and($action->note)->toBe('Three reports, two warnings.')
        ->and($audit->action)->toBe(AuditAction::SanctionApplied)
        ->and($audit->actor_id)->toBe($this->admin->id)
        ->and($audit->before)->toEqual(['status' => 'active', 'reason' => null, 'until' => null])
        ->and($audit->after)->toEqual(['status' => 'suspended', 'reason' => 'Harassment in comments', 'until' => '2026-10-08T12:00:00+00:00'])
        ->and($audit->context)->toEqual(['sanction' => 'suspension', 'reason_code' => 'harassment', 'days' => 7])
        ->and(array_column($this->securityEvents(), 'message'))->toContain('moderation.sanction_applied');

    Notification::assertSentTo($this->target, AccountSuspendedNotification::class, fn ($n) => $n->reason === 'Harassment in comments' && $n->endsAt->equalTo($sanction->expires_at));
});

it('applies a suspension on the account\'s next request', function () {
    $this->actingAs($this->target)->get('/settings/profile')->assertOk();

    $this->sanctions->suspend($this->admin, $this->target, sanctionInput());

    $this->actingAs($this->target->fresh())->get('/')->assertRedirect('/account/suspended');
});

it('bans: ends the account\'s sessions and remember token, not the admin\'s, and emails', function () {
    $this->target->forceFill(['remember_token' => 'old-token'])->save();
    DB::table('sessions')->insert([
        ['id' => 'target-session', 'user_id' => $this->target->id, 'payload' => '', 'last_activity' => time()],
        ['id' => 'admin-session', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => time()],
    ]);

    $sanction = $this->sanctions->ban($this->admin, $this->target, sanctionInput(null, 'Account trading'));

    $this->target->refresh();
    expect($this->target->status)->toBe(UserStatus::Banned)
        ->and($this->target->status_expires_at)->toBeNull()
        ->and($this->target->remember_token)->not->toBe('old-token')
        ->and($sanction->expires_at)->toBeNull()
        ->and(ModerationAction::query()->sole()->action)->toBe(ModerationActionType::Ban)
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['admin-session']);

    Notification::assertSentTo($this->target, AccountBannedNotification::class, fn ($n) => $n->reason === 'Account trading');
    $this->actingAs($this->target)->get('/')->assertRedirect('/login');
});

it('replaces an active suspension with a new suspension, keeping one active', function () {
    $first = $this->sanctions->suspend($this->admin, $this->target, sanctionInput(7));
    Date::setTestNow('2026-10-02 12:00:00');
    $second = $this->sanctions->suspend($this->admin, $this->target, sanctionInput(30, 'Longer this time'));

    $first->refresh();
    expect($first->lifted_by)->toBe($this->admin->id)
        ->and($first->lifted_at->toIso8601String())->toBe('2026-10-02T12:00:00+00:00')
        ->and(UserSanction::query()->active()->pluck('id')->all())->toBe([$second->id])
        ->and($this->target->fresh()->status_expires_at->toIso8601String())->toBe('2026-11-01T12:00:00+00:00')
        ->and(ModerationAction::query()->where('target_type', ModerationAction::TARGET_SANCTION)->sole()->note)->toBe('Replaced by a new suspension.')
        ->and(AuditLog::query()->latest('id')->first()->context['replaced'])->toBe('suspension');
});

it('replaces an active suspension with a ban', function () {
    $suspension = $this->sanctions->suspend($this->admin, $this->target, sanctionInput());
    $ban = $this->sanctions->ban($this->admin, $this->target, sanctionInput(null));

    expect($suspension->fresh()->lifted_at)->not->toBeNull()
        ->and(UserSanction::query()->active()->pluck('id')->all())->toBe([$ban->id])
        ->and($this->target->fresh()->status)->toBe(UserStatus::Banned);
});

it('stores a ban without a length, whatever days the caller passes', function () {
    $this->sanctions->ban($this->admin, $this->target, sanctionInput(-3));

    expect(ModerationAction::query()->sole()->duration_hours)->toBeNull()
        ->and(UserSanction::query()->sole()->expires_at)->toBeNull()
        ->and(AuditLog::query()->sole()->context)->not->toHaveKey('days');
});

it('re-checks the rank on the locked account, so a role change in flight is caught', function () {
    $sanctions = $this->sanctions;
    // The caller's copy still says `user`; the row says `admin` by the time it is locked.
    $stale = $this->target->replicate()->forceFill(['id' => $this->target->id, 'ulid' => $this->target->ulid]);
    $stale->exists = true;
    $this->target->forceFill(['role' => Role::Admin])->save();

    expect(fn () => $sanctions->suspend($this->admin, $stale, sanctionInput()))->toThrow(AuthorizationException::class)
        ->and(UserSanction::query()->count())->toBe(0);
});

it('refuses a new suspension or ban on a banned, pending-deletion or deleted account', function (string $state) {
    $target = match ($state) {
        'banned' => User::factory()->banned()->create(),
        'pending deletion' => User::factory()->pendingDeletion()->create(),
        'deleted' => tap(User::factory()->create())->delete(),
    };

    expect(fn () => $this->sanctions->suspend($this->admin, $target, sanctionInput()))->toThrow(SanctionRefused::class)
        ->and(fn () => $this->sanctions->ban($this->admin, $target, sanctionInput(null)))->toThrow(SanctionRefused::class)
        ->and(ModerationAction::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
})->with(['banned', 'pending deletion', 'deleted']);

it('lifts a suspension with the lift action and emails', function () {
    $sanction = $this->sanctions->suspend($this->admin, $this->target, sanctionInput());
    $this->sanctions->lift($this->admin, $this->target, 'Appeal accepted by email.');

    $lift = ModerationAction::query()->where('target_type', ModerationAction::TARGET_SANCTION)->sole();
    expect($this->target->fresh()->status)->toBe(UserStatus::Active)
        ->and($sanction->fresh()->lifted_by)->toBe($this->admin->id)
        ->and($lift->action)->toBe(ModerationActionType::Lift)
        ->and($lift->target_id)->toBe($sanction->id)
        ->and($lift->note)->toBe('Appeal accepted by email.')
        ->and(AuditLog::query()->latest('id')->first()->action)->toBe(AuditAction::SanctionLifted);

    Notification::assertSentTo($this->target, SanctionEndedNotification::class, fn ($n) => $n->type === SanctionType::Suspension && $n->expired === false);
});

it('lifts a ban with the unban action', function () {
    $this->sanctions->ban($this->admin, $this->target, sanctionInput(null));
    $this->sanctions->lift($this->admin, $this->target, 'Wrong account.');

    expect($this->target->fresh()->status)->toBe(UserStatus::Active)
        ->and(ModerationAction::query()->where('target_type', ModerationAction::TARGET_SANCTION)->sole()->action)->toBe(ModerationActionType::Unban);
});

it('refuses to lift when nothing is active, including a suspension that ran out', function () {
    expect(fn () => $this->sanctions->lift($this->admin, $this->target, 'Nothing here.'))->toThrow(SanctionRefused::class);

    $this->sanctions->suspend($this->admin, $this->target, sanctionInput(1));
    $this->travel(2)->days();

    expect(fn () => $this->sanctions->lift($this->admin, $this->target, 'Too late.'))->toThrow(SanctionRefused::class);
});

it('requires the message, the note and a valid length in the service itself', function (Closure $call) {
    expect(fn () => $call($this->sanctions, $this->admin, $this->target))->toThrow(InvalidArgumentException::class)
        ->and(UserSanction::query()->count())->toBe(0);
})->with([
    'blank message' => [fn ($s, $a, $t) => $s->suspend($a, $t, sanctionInput(7, '   '))],
    'blank note' => [fn ($s, $a, $t) => $s->ban($a, $t, sanctionInput(null, 'Reason', ''))],
    'too long message' => [fn ($s, $a, $t) => $s->ban($a, $t, sanctionInput(null, str_repeat('a', 256)))],
    'zero days' => [fn ($s, $a, $t) => $s->suspend($a, $t, sanctionInput(0))],
    'too many days' => [fn ($s, $a, $t) => $s->suspend($a, $t, sanctionInput(91))],
    'no days' => [fn ($s, $a, $t) => $s->suspend($a, $t, sanctionInput(null))],
    'blank lift note' => [fn ($s, $a, $t) => $s->lift($a, $t, ' ')],
]);

it('enforces the rank rule in the service', function () {
    $other = User::factory()->admin()->create();

    $this->sanctions->suspend(User::factory()->superAdmin()->create(), $other, sanctionInput());

    expect(fn () => $this->sanctions->suspend($this->admin, $other, sanctionInput()))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->sanctions->ban($this->admin, $other, sanctionInput(null)))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->sanctions->lift($this->admin, $other, 'Equal rank.'))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->sanctions->lift($this->admin, $this->admin, 'Self.'))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->sanctions->suspend($this->admin, $this->admin, sanctionInput()))->toThrow(AuthorizationException::class)
        ->and(fn () => $this->sanctions->ban(User::factory()->moderator()->create(), $this->target, sanctionInput(null)))->toThrow(AuthorizationException::class);

    expect($other->fresh()->status)->toBe(UserStatus::Suspended);
});

it('drops the cached public profile on every change', function () {
    $version = CacheInvalidator::profileVersion('chief');

    $this->sanctions->suspend($this->admin, $this->target, sanctionInput());
    $this->sanctions->lift($this->admin, $this->target, 'Done.');

    expect(CacheInvalidator::profileVersion('chief'))->toBe($version + 2);
});

it('expires suspensions past their end once, with the scheduler as actor', function () {
    $sanction = $this->sanctions->suspend($this->admin, $this->target, sanctionInput(3));
    $this->travel(3)->days();

    $this->artisan('moderation:expire-sanctions')->assertSuccessful()->expectsOutputToContain('1 sanction ended.');
    $this->artisan('moderation:expire-sanctions')->assertSuccessful()->expectsOutputToContain('0 sanctions ended.');

    $audit = AuditLog::query()->latest('id')->first();
    expect($this->target->fresh()->status)->toBe(UserStatus::Active)
        ->and($sanction->fresh()->lifted_at)->toBeNull()
        ->and($audit->action)->toBe(AuditAction::SanctionExpired)
        ->and($audit->actor_id)->toBeNull()
        ->and($audit->context)->toEqual(['via' => 'scheduler', 'sanction' => 'suspension']);

    Notification::assertSentToTimes($this->target, SanctionEndedNotification::class, 1);
    Notification::assertSentTo($this->target, SanctionEndedNotification::class, fn ($n) => $n->expired === true);
});

it('leaves running suspensions and bans to the expiry job', function () {
    $this->sanctions->suspend($this->admin, $this->target, sanctionInput(3));
    $banned = User::factory()->create();
    $this->sanctions->ban($this->admin, $banned, sanctionInput(null));
    $this->travel(2)->days();

    $this->artisan('moderation:expire-sanctions')->expectsOutputToContain('0 sanctions ended.');

    expect($this->target->fresh()->status)->toBe(UserStatus::Suspended)
        ->and($banned->fresh()->status)->toBe(UserStatus::Banned);
});

it('schedules the expiry every 15 minutes, never at :00', function () {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($e) => str_contains($e->command, 'moderation:expire-sanctions'));

    expect($event->expression)->toBe('7-59/15 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('lists the history newest first with states and lift notes', function () {
    $first = $this->sanctions->suspend($this->admin, $this->target, sanctionInput(1, 'First'));
    $this->sanctions->lift($this->admin, $this->target, 'Mistake.');
    $this->sanctions->suspend($this->admin, $this->target, sanctionInput(1, 'Second'));
    $this->travel(2)->days();
    $this->sanctions->ban($this->admin, $this->target, sanctionInput(null, 'Third'));

    $history = app(SanctionHistoryQuery::class)->forUser($this->target, 10);

    expect(array_column(array_map(fn ($s) => $s->toArray(), $history), 'publicReason'))->toBe(['Third', 'Second', 'First'])
        ->and(array_column(array_map(fn ($s) => $s->toArray(), $history), 'state'))->toBe(['active', 'ended', 'lifted'])
        ->and($history[2]->liftNote)->toBe('Mistake.')
        ->and($history[2]->liftedBy)->toBe('warden')
        ->and($history[0]->issuedBy)->toBe('warden')
        ->and($first->id)->toBeInt();
});

it('reports what the viewer may do', function () {
    $abilities = fn () => $this->sanctions->abilities($this->admin, $this->target->fresh())->toArray();

    expect($abilities())->toBe(['suspend' => true, 'ban' => true, 'lift' => false, 'activeType' => null]);

    $this->sanctions->suspend($this->admin, $this->target, sanctionInput());
    expect($abilities())->toBe(['suspend' => true, 'ban' => true, 'lift' => true, 'activeType' => 'suspension']);

    $this->sanctions->ban($this->admin, $this->target, sanctionInput(null));
    expect($abilities())->toBe(['suspend' => false, 'ban' => false, 'lift' => true, 'activeType' => 'ban'])
        ->and($this->sanctions->abilities(User::factory()->admin()->restricted()->create(), $this->target->fresh())->toArray())
        ->toBe(['suspend' => false, 'ban' => false, 'lift' => false, 'activeType' => 'ban']);
});

it('keeps moderation actions append-only', function () {
    $this->sanctions->suspend($this->admin, $this->target, sanctionInput());
    $action = ModerationAction::query()->sole();

    expect(fn () => $action->forceFill(['note' => 'edited'])->save())->toThrow(ModerationActionIsImmutable::class)
        ->and(fn () => DB::transaction(fn () => DB::table('moderation_actions')->update(['note' => 'edited'])))->toThrow(QueryException::class, 'append-only')
        ->and(fn () => DB::transaction(fn () => DB::table('moderation_actions')->delete()))->toThrow(QueryException::class, 'append-only');
});

it('reads its limits from config', function () {
    expect(config('moderation.sanctions'))->toBe(['suspension_max_days' => 90, 'public_reason_max' => 255, 'note_max' => 2000]);
});
