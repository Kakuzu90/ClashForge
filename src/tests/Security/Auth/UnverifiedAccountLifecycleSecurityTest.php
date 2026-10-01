<?php

use App\Domain\Audit\Data\AuditActorData;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Data\UsernameFieldRules;
use App\Domain\Auth\Enums\EmailVerificationOutcome;
use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Jobs\SendVerificationLifecycleEmailJob;
use App\Domain\Auth\Notifications\VerifyEmailNotification;
use App\Domain\Auth\Services\AccountDeletionService;
use App\Domain\Auth\Services\AuthenticationService;
use App\Domain\Auth\Services\EmailChangeService;
use App\Domain\Auth\Services\EmailVerificationService;
use App\Domain\Auth\Services\PasswordChangeService;
use App\Domain\Auth\Services\RoleAssignmentService;
use App\Domain\Auth\Services\SessionService;
use App\Domain\Auth\Services\UnverifiedAccountLifecycleService;
use App\Domain\Moderation\Data\ApplySanctionData;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\Moderation\Services\SanctionService;
use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\EmailDelivery;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Services\Notifier;
use App\Domain\Users\Data\UpdatePrivacyData;
use App\Domain\Users\Data\UpdateProfileData;
use App\Domain\Users\Enums\ProfileVisibility;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Services\PrivacySettingsService;
use App\Domain\Users\Services\ProfileService;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Validator;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\Auth\InteractsWithBrowsers;

uses(InteractsWithBrowsers::class);

beforeEach(function () {
    Date::setTestNow('2026-10-01 12:00:00');
    Queue::fake();
    Mail::fake();
    Notification::fake();
});

it('authorizes only the trusted system for eligible ordinary users', function (Role $role) {
    $account = User::factory()->warnedUnverified()->create(['role' => $role]);
    $actor = User::factory()->superAdmin()->create();
    expect(Gate::forUser($actor)->allows('processUnverifiedLifecycle', $account))->toBeFalse()
        ->and(Gate::forUser($account)->allows('expireUnverified', $account))->toBeFalse()
        ->and(Gate::forUser(null)->allows('processUnverifiedLifecycle', $account))->toBe($role === Role::User)
        ->and(app(AccountDeletionService::class)->purgeUnverified($account->id))->toBe($role === Role::User);
})->with(Role::cases());

it('includes sanctioned ordinary users but excludes pending self-deletion', function (UserStatus $status) {
    $account = User::factory()->warnedUnverified()->create(['status' => $status]);
    $eligible = $status !== UserStatus::PendingDeletion;
    expect(app(UnverifiedAccountLifecycleService::class)->process()['purged'])->toBe((int) $eligible)
        ->and(User::withTrashed()->findOrFail($account->id)->deleted_at !== null)->toBe($eligible);
})->with(UserStatus::cases());

it('skips staff and pending accounts before sending reminders or warnings', function (string $state) {
    $account = User::factory()->unverified()->{$state}()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_warning_days'))]);
    expect(app(UnverifiedAccountLifecycleService::class)->process())->toBe(['reminders' => 0, 'warnings' => 0, 'purged' => 0]);
    Queue::assertNothingPushed();
    Mail::assertNothingSent();
    expect($account->refresh()->verification_warning_queued_at)->toBeNull();
})->with(['moderator', 'admin', 'superAdmin', 'pendingDeletion']);

it('cancels queued mail and purge when verification wins', function () {
    $account = User::factory()->warnedUnverified()->create();
    $job = new SendVerificationLifecycleEmailJob($account->id, UnverifiedAccountLifecycleService::emailBinding($account), true, (string) $account->verification_notice_key);
    $outcome = app(EmailVerificationService::class)->confirm($account->ulid, sha1(strtolower($account->email)), null, null, null);
    expect($outcome)->toBe(EmailVerificationOutcome::Verified)
        ->and(app(AccountDeletionService::class)->purgeUnverified($account->id))->toBeFalse();
    $job->handle(app(UnverifiedAccountLifecycleService::class));
    expect($account->refresh()->email_verified_at)->not->toBeNull();
    Mail::assertNothingSent();
});

it('rejects a stale verification link after purge without reviving the tombstone', function () {
    $account = User::factory()->warnedUnverified()->create();
    $hash = sha1(strtolower($account->email));
    $link = VerifyEmailNotification::url($account);
    expect(app(AccountDeletionService::class)->purgeUnverified($account->id))->toBeTrue();
    expect(app(EmailVerificationService::class)->confirm($account->ulid, $hash, null, $account, 'stale-session'))->toBe(EmailVerificationOutcome::Invalid);
    $this->get($link)->assertOk()->assertInertia(fn (Assert $page) => $page->where('outcome', 'invalid')->where('confirmUrl', null));
    $this->post($link)->assertRedirect('/email/verified');
    $tombstone = User::withTrashed()->findOrFail($account->id);
    expect($tombstone->email_verified_at)->toBeNull()->and($tombstone->password)->toBeNull()->and($tombstone->deleted_at)->not->toBeNull();
});

it('skips delayed jobs after identity or eligibility changes', function (string $change) {
    $account = User::factory()->unverified()->create(['verification_warning_queued_at' => now()]);
    $job = new SendVerificationLifecycleEmailJob($account->id, UnverifiedAccountLifecycleService::emailBinding($account), true, (string) $account->verification_notice_key);
    $account->forceFill(match ($change) {
        'email' => ['email' => 'different@example.com'],
        'verified' => ['email_verified_at' => now()],
        'staff' => ['role' => Role::Admin],
        'pending' => ['status' => UserStatus::PendingDeletion],
        'deleted' => ['deleted_at' => now()],
    })->save();
    $job->handle(app(UnverifiedAccountLifecycleService::class));
    Mail::assertNothingSent();
})->with(['email', 'verified', 'staff', 'pending', 'deleted']);

it('suppresses a queued reminder when a final warning has superseded it', function () {
    $account = User::factory()->unverified()->create(['verification_reminder_queued_at' => now()->subDays(24), 'verification_warning_queued_at' => now()]);
    (new SendVerificationLifecycleEmailJob($account->id, UnverifiedAccountLifecycleService::emailBinding($account), false, (string) $account->verification_notice_key))->handle(app(UnverifiedAccountLifecycleService::class));
    Mail::assertNothingSent();
});

it('retains sanctions and their audit records while clearing sessions and notification data', function () {
    $this->useDatabaseSessions();
    $actor = User::factory()->admin()->create();
    $account = User::factory()->warnedUnverified()->create();
    $cookies = $this->signInBrowser($account, remember: true);
    $sanction = app(SanctionService::class)->suspend($actor, $account, new ApplySanctionData(ReasonCode::Harassment, 'Harassment', 'Retain evidence', 7));
    NotificationPreference::factory()->create(['user_id' => $account->id]);
    EmailDelivery::factory()->create(['user_id' => $account->id]);
    app(Notifier::class)->send($account, new InAppMessageData(NotificationType::PasswordChanged));
    $audit = AuditLog::query()->where('action', 'sanction.applied')->sole()->getAttributes();
    expect(app(AccountDeletionService::class)->purgeUnverified($account->id))->toBeTrue()
        ->and(UserSanction::query()->findOrFail($sanction->id)->issued_by)->toBe($actor->id)
        ->and(ModerationAction::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'sanction.applied')->sole()->getAttributes())->toBe($audit)
        ->and(DB::table('sessions')->where('user_id', $account->id)->count())->toBe(0);
    $this->assertDatabaseCount('notification_preferences', 0);
    $this->assertDatabaseCount('notification_email_deliveries', 0);
    $this->assertDatabaseCount('notifications', 0);
    $this->browser($cookies)->get('/settings/profile')->assertRedirect('/login');
    expect(app(Notifier::class)->send($account, new InAppMessageData(NotificationType::PasswordChanged)))->toBeNull();
});

it('guards lifecycle progress from mass assignment and permanently holds the purged username', function () {
    $account = User::factory()->warnedUnverified()->create(['username' => 'never_chief']);
    expect($account->isFillable('verification_warning_sent_at'))->toBeFalse()
        ->and($account->isFillable('verification_warning_queued_at'))->toBeFalse();
    app(AccountDeletionService::class)->purgeUnverified($account->id);
    expect(Validator::make(['username' => 'never_chief'], ['username' => UsernameFieldRules::forRegistration()])->fails())->toBeTrue();
});

it('logs job progress without the address verification URL or binding', function () {
    Log::spy();
    $account = User::factory()->unverified()->create(['verification_reminder_queued_at' => now()]);
    $job = new SendVerificationLifecycleEmailJob($account->id, UnverifiedAccountLifecycleService::emailBinding($account), false, (string) $account->verification_notice_key);
    $job->handle(app(UnverifiedAccountLifecycleService::class));
    Log::shouldHaveReceived('info')->with('auth.verification_lifecycle_email_started', ['user_id' => $account->id, 'warning' => false])->once();
    Log::shouldHaveReceived('info')->with('auth.verification_lifecycle_email_finished', Mockery::on(fn ($context) => isset($context['duration_seconds']) && count($context) === 3))->once();
});

it('requeues a skipped warning after self-deletion is cancelled without accepting stale dispatches', function () {
    $account = User::factory()->unverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_warning_days'))]);
    $service = app(UnverifiedAccountLifecycleService::class);
    $service->process();
    $account->refresh();
    $old = new SendVerificationLifecycleEmailJob($account->id, $service::emailBinding($account), true, $account->verification_notice_key);
    app(AccountDeletionService::class)->request($account, 'password');
    $old->handle($service);
    expect($account->refresh()->verification_warning_queued_at)->toBeNull()->and($account->verification_notice_key)->toBeNull();
    app(AuthenticationService::class)->attempt($account->email, 'password');
    expect($service->process()['warnings'])->toBe(1);
    $account->refresh();
    $current = new SendVerificationLifecycleEmailJob($account->id, $service::emailBinding($account), true, $account->verification_notice_key);
    expect($current->dispatchKey)->not->toBe($old->dispatchKey);
    $old->handle($service);
    expect($account->refresh()->verification_notice_key)->toBe($current->dispatchKey);
    Mail::assertNothingSent();
    $current->handle($service);
    $old->handle($service);
    $current->handle($service);
    Mail::assertSentCount(1);
    $this->travel((int) config('platform.auth.unverified_warning_grace_days'))->days();
    expect($service->process()['purged'])->toBe(1);
});

it('does not repopulate PII or credentials through stale settings objects after purge', function (string $write) {
    $stale = User::factory()->warnedUnverified()->withPendingEmail('old-pending@example.com')->create();
    $this->actingAs($stale);
    app(AccountDeletionService::class)->purgeUnverified($stale->id);
    $action = match ($write) {
        'profile' => fn () => app(ProfileService::class)->update($stale, UpdateProfileData::fromValidated('Restored name', 'Restored bio', null, [], null, [])),
        'privacy' => fn () => app(PrivacySettingsService::class)->update($stale, new UpdatePrivacyData(ProfileVisibility::Private, false, false, false, false)),
        'avatar' => fn () => app(ProfileService::class)->removeAvatar($stale),
        'email' => fn () => app(EmailChangeService::class)->request($stale, 'restored@example.com', 'password', null),
        'resend' => fn () => app(EmailChangeService::class)->resend($stale, 'password', null),
        'confirm' => fn () => app(EmailChangeService::class)->confirm($stale->ulid, sha1('old-pending@example.com'), $stale, null, null),
        'password' => fn () => app(PasswordChangeService::class)->change($stale, 'password', 'new-password', null),
        'session' => fn () => app(SessionService::class)->revokeOthers($stale, null),
    };
    expect($action)->toThrow(ModelNotFoundException::class);
    app(AuthenticationService::class)->recordLogin($stale, '127.0.0.1');
    $fresh = User::withTrashed()->findOrFail($stale->id);
    expect($fresh->profile?->bio)->toBeNull()->and($fresh->pending_email)->toBeNull()->and($fresh->password)->toBeNull()
        ->and($fresh->remember_token)->toBeNull()->and($fresh->last_login_at)->toBeNull()->and($fresh->last_login_ip_hash)->toBeNull()
        ->and(PrivacySettings::query()->findOrFail($stale->id)->profile_visibility)->toBe(ProfileVisibility::Public);
})->with(['profile', 'privacy', 'avatar', 'email', 'resend', 'confirm', 'password', 'session']);

it('refuses late session persistence for a stale authenticated request', function () {
    $this->useDatabaseSessions();
    $stale = User::factory()->warnedUnverified()->create();
    $this->actingAs($stale);
    $handler = app('session')->driver()->getHandler();
    app(AccountDeletionService::class)->purgeUnverified($stale->id);
    expect($handler->write('late-session', 'private-session-data'))->toBeFalse()
        ->and(DB::table('sessions')->where('user_id', $stale->id)->count())->toBe(0);
});

it('rearms a skipped warning when an exempt staff account becomes an ordinary user again', function () {
    $account = User::factory()->unverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_warning_days'))]);
    $service = app(UnverifiedAccountLifecycleService::class);
    $service->process();
    $account->refresh();
    $old = new SendVerificationLifecycleEmailJob($account->id, $service::emailBinding($account), true, $account->verification_notice_key);
    $roles = app(RoleAssignmentService::class);
    $roles->assign($account, Role::Admin, AuditActorData::console());
    $old->handle($service);
    expect($account->refresh()->verification_warning_queued_at)->toBeNull();
    $roles->assign($account, Role::User, AuditActorData::console());
    expect($service->process()['warnings'])->toBe(1);
    Mail::assertNothingSent();
});
