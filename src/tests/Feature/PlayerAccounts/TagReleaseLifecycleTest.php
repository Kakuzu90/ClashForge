<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\Notification;
use App\Domain\PlayerAccounts\Enums\CocAccountStatus;
use App\Domain\PlayerAccounts\Enums\DisputeRefusal;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Services\DisputeService;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

// P2-24: tags released at the end of the deletion window and 30 days into a ban (specs/13 §6), and a
// deletion held while a dispute involves the user (specs/23 §1).

beforeEach(function () {
    Date::setTestNow('2026-10-05 04:00:00');
});

function deletionDue(): User
{
    return User::factory()->pendingDeletion(now()->subDays((int) config('platform.auth.deletion_grace_days')))->create();
}

function openDisputeAgainst(User $holder, string $tag = '#2PQ8GRJC'): string
{
    $claimant = User::factory()->create();

    return (string) app(DisputeService::class)->open($claimant, PlayerTag::from($tag), 'I lost the phone with this account.')->disputeUlid;
}

it('releases every tag the user held when the deletion window ends (specs/13 §6, specs/08 §6)', function () {
    Queue::fake();
    $user = deletionDue();
    $verified = CocAccount::factory()->for($user)->forTag('#2PQ8GRJC')->verified()->featured()->create();
    $unverified = CocAccount::factory()->for($user)->forTag('#8LQ9JPY2')->create();
    $suspended = CocAccount::factory()->for($user)->forTag('#9VJ2YRQP')->state(['status' => CocAccountStatus::Suspended])->create();
    $user->forceFill(['verified_accounts_count' => 1])->save();

    $this->artisan('platform:anonymize-deleted')->expectsOutputToContain('Anonymised 1 accounts.')->assertSuccessful();

    foreach ([$verified, $unverified] as $row) {
        expect($row->refresh()->status)->toBe(CocAccountStatus::Released)
            ->and($row->user_id)->toBeNull()
            ->and($row->is_featured)->toBeFalse();
    }
    $audits = AuditLog::query()->where('action', AuditAction::CocAccountReleased)->get();
    expect($suspended->refresh()->status)->toBe(CocAccountStatus::Suspended)
        ->and(User::withTrashed()->findOrFail($user->id)->verified_accounts_count)->toBe(0)
        ->and($audits)->toHaveCount(2)
        ->and($audits->pluck('context.reason')->unique()->all())->toBe(['deletion'])
        ->and($audits->pluck('actor_id')->filter()->all())->toBe([])
        ->and(Notification::query()->where('notifiable_id', $user->id)->exists())->toBeFalse();
});

it('releases a tag the next user can attach again, with its history', function () {
    Queue::fake();
    $user = deletionDue();
    $row = CocAccount::factory()->for($user)->forTag('#2PQ8GRJC')->verified()->create();

    $this->artisan('platform:anonymize-deleted')->assertSuccessful();

    expect(CocAccount::query()->where('tag_normalized', '2PQ8GRJC')->where('status', CocAccountStatus::Released)->whereNull('user_id')->sole()->id)->toBe($row->id);
});

it('holds the deletion while the user holds a disputed tag, then runs once it is resolved (specs/23 §1)', function () {
    Queue::fake();
    $holder = User::factory()->create();
    $row = CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->create();
    $dispute = openDisputeAgainst($holder);
    $holder->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()->subDays((int) config('platform.auth.deletion_grace_days'))])->save();

    $this->artisan('platform:anonymize-deleted')->expectsOutputToContain('Anonymised 0 accounts.')->assertSuccessful();
    expect($holder->refresh()->status)->toBe(UserStatus::PendingDeletion)
        ->and($row->refresh()->status)->toBe(CocAccountStatus::Disputed);

    $claimant = User::query()->whereKeyNot($holder->id)->sole();
    app(DisputeService::class)->withdraw($claimant, $dispute);
    $this->artisan('platform:anonymize-deleted')->expectsOutputToContain('Anonymised 1 accounts.')->assertSuccessful();

    expect(User::withTrashed()->findOrFail($holder->id)->deleted_at)->not->toBeNull()
        ->and($row->refresh()->status)->toBe(CocAccountStatus::Released);
});

it('holds the deletion of a claimant with a running dispute too', function () {
    Queue::fake();
    $holder = User::factory()->create();
    CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->create();
    openDisputeAgainst($holder);
    $claimant = User::query()->whereKeyNot($holder->id)->sole();
    $claimant->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()->subDays((int) config('platform.auth.deletion_grace_days'))])->save();

    $this->artisan('platform:anonymize-deleted')->expectsOutputToContain('Anonymised 0 accounts.')->assertSuccessful();

    expect($claimant->refresh()->status)->toBe(UserStatus::PendingDeletion);
});

it('refuses a dispute opened after the holder asked for deletion, so nobody can push it back', function () {
    Queue::fake();
    $holder = User::factory()->pendingDeletion(now()->subDays((int) config('platform.auth.deletion_grace_days')))->create();
    $row = CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->create();

    $result = app(DisputeService::class)->open(User::factory()->create(), PlayerTag::from('#2PQ8GRJC'), 'I lost the phone with this account.');

    expect($result->refusal)->toBe(DisputeRefusal::NotHeld)
        ->and($row->refresh()->status)->toBe(CocAccountStatus::Verified);
    $this->artisan('platform:anonymize-deleted')->expectsOutputToContain('Anonymised 1 accounts.')->assertSuccessful();
    expect($row->refresh()->status)->toBe(CocAccountStatus::Released);
});

it('holds while the dispute waits on the admins too', function () {
    Queue::fake();
    $holder = User::factory()->create();
    CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->create();
    app(DisputeService::class)->respond($holder, openDisputeAgainst($holder), 'It is mine.');
    $holder->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()->subDays((int) config('platform.auth.deletion_grace_days'))])->save();

    $this->artisan('platform:anonymize-deleted')->expectsOutputToContain('Anonymised 0 accounts.')->assertSuccessful();

    expect($holder->refresh()->status)->toBe(UserStatus::PendingDeletion);
});

it('still lets a held user cancel by signing in (specs/23 §1)', function () {
    $holder = User::factory()->create(['password' => 'password']);
    CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->create();
    openDisputeAgainst($holder);
    $holder->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()->subDays((int) config('platform.auth.deletion_grace_days')), 'deletion_previous_status' => UserStatus::Active])->save();

    $this->post('/login', ['email' => $holder->email, 'password' => 'password'])->assertRedirect('/');

    expect($holder->refresh()->status)->toBe(UserStatus::Active)->and($holder->deletion_requested_at)->toBeNull();
});

it('releases the tags of a never-verified account when it is purged (P1-16)', function () {
    Queue::fake();
    $account = User::factory()->warnedUnverified()->create();
    $row = CocAccount::factory()->for($account)->forTag('#2PQ8GRJC')->create();

    $this->artisan('auth:process-unverified')->expectsOutputToContain('1 purged accounts.')->assertSuccessful();

    expect($row->refresh()->status)->toBe(CocAccountStatus::Released)
        ->and($row->user_id)->toBeNull()
        ->and(AuditLog::query()->where('action', AuditAction::CocAccountReleased)->sole()->context['reason'])->toBe('deletion');
});

it('tells the user on the Danger zone, and on the request, that a dispute holds the deletion', function () {
    $holder = User::factory()->create();
    CocAccount::factory()->for($holder)->forTag('#2PQ8GRJC')->verified()->create();
    openDisputeAgainst($holder);

    $this->actingAs($holder)->get('/settings/danger-zone')
        ->assertInertia(fn (Assert $page) => $page->component('Settings/DangerZone')
            ->where('holds', ['You are part of an ownership dispute that is still open. Your account is deleted once it is resolved.']));

    $this->actingAs($holder)->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'password'])
        ->assertRedirect('/login')
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'An ownership dispute is still open'));
    expect($holder->refresh()->status)->toBe(UserStatus::PendingDeletion);
});

it('shows no hold when nothing involves the user', function () {
    $this->actingAs(User::factory()->create())->get('/settings/danger-zone')
        ->assertInertia(fn (Assert $page) => $page->where('holds', []));
});

function bannedFor(int $days): User
{
    $user = User::factory()->banned()->create();
    UserSanction::factory()->ban()->create(['user_id' => $user->id, 'starts_at' => now()->subDays($days)]);

    return $user;
}

it('releases a banned owner\'s tags once the ban has run its days, with the notice (specs/13 §6)', function () {
    $days = (int) config('coc.accounts.ban_release_days');
    $user = bannedFor($days + 1);
    $verified = CocAccount::factory()->for($user)->forTag('#2PQ8GRJC')->verified()->featured()->create();
    $unverified = CocAccount::factory()->for($user)->forTag('#8LQ9JPY2')->create();

    $this->artisan('coc:release-banned-tags')->expectsOutputToContain('Released 2 accounts.')->assertSuccessful();

    expect($verified->refresh()->status)->toBe(CocAccountStatus::Released)
        ->and($verified->user_id)->toBeNull()
        ->and($unverified->refresh()->status)->toBe(CocAccountStatus::Released)
        ->and($user->refresh()->verified_accounts_count)->toBe(0);
    $audit = AuditLog::query()->where('action', AuditAction::CocAccountReleased)->first();
    expect($audit->context['reason'])->toBe('ban')
        ->and($audit->actor_id)->toBeNull()
        ->and(Notification::query()->where('notifiable_id', $user->id)->where('type', NotificationType::CocAccountReleased->value)->count())->toBe(2);

    $this->artisan('coc:release-banned-tags')->expectsOutputToContain('Released 0 accounts.')->assertSuccessful();
});

it('waits the full ban period', function () {
    $user = bannedFor((int) config('coc.accounts.ban_release_days') - 1);
    $row = CocAccount::factory()->for($user)->verified()->create();

    $this->artisan('coc:release-banned-tags')->expectsOutputToContain('Released 0 accounts.')->assertSuccessful();

    expect($row->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('keeps the tags of a user whose ban was lifted', function () {
    $user = bannedFor((int) config('coc.accounts.ban_release_days') + 5);
    UserSanction::query()->where('user_id', $user->id)->update(['lifted_at' => now()->subDay(), 'lifted_by' => User::factory()->admin()->create()->id]);
    $user->forceFill(['status' => UserStatus::Active])->save();
    $row = CocAccount::factory()->for($user)->verified()->create();

    $this->artisan('coc:release-banned-tags')->assertSuccessful();

    expect($row->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('counts the days from the current ban, not an earlier lifted one', function () {
    $user = bannedFor(10);
    UserSanction::factory()->ban()->create(['user_id' => $user->id, 'starts_at' => now()->subDays(90), 'lifted_at' => now()->subDays(60)]);
    $row = CocAccount::factory()->for($user)->verified()->create();

    $this->artisan('coc:release-banned-tags')->assertSuccessful();

    expect($row->refresh()->status)->toBe(CocAccountStatus::Verified);
});

it('leaves a disputed row to its dispute and a suspended one to staff', function () {
    $user = bannedFor((int) config('coc.accounts.ban_release_days') + 1);
    $disputed = CocAccount::factory()->for($user)->forTag('#2PQ8GRJC')->disputed()->create();
    $suspended = CocAccount::factory()->for($user)->forTag('#8LQ9JPY2')->state(['status' => CocAccountStatus::Suspended])->create();

    $this->artisan('coc:release-banned-tags')->expectsOutputToContain('Released 0 accounts.')->assertSuccessful();

    expect($disputed->refresh()->status)->toBe(CocAccountStatus::Disputed)
        ->and($suspended->refresh()->status)->toBe(CocAccountStatus::Suspended);
});

it('schedules the release daily at 04:15 with overlap and single-server protection', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'coc:release-banned-tags'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('15 4 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue()
        ->and(config('coc.accounts.ban_release_days'))->toBe(30);
});
