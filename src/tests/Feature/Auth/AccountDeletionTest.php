<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Models\UsernameHistory;
use App\Domain\Auth\Services\AccountDeletionService;
use App\Domain\Auth\Services\AuthenticationService;
use App\Domain\Auth\Services\PasswordResetService;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaLifecycleService;
use App\Domain\Moderation\Data\ApplySanctionData;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Models\ModerationAction;
use App\Domain\Moderation\Models\UserSanction;
use App\Domain\Moderation\Services\SanctionService;
use App\Domain\Notifications\Data\InAppMessageData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\Notifier;
use App\Domain\Users\Models\PrivacySettings;
use App\Domain\Users\Models\Profile;
use App\Domain\Users\Models\UserStats;
use App\Domain\Users\Services\CacheInvalidator;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Fortify\Actions\CompletePasswordReset;
use Tests\Support\Auth\FakesHibp;
use Tests\Support\Auth\InteractsWithBrowsers;

uses(InteractsWithBrowsers::class, FakesHibp::class);

beforeEach(function () {
    Notification::fake();
    Date::setTestNow('2026-10-01 12:00:00');
});

it('renders the Danger zone with a server-computed ability and the configured grace period', function () {
    $this->actingAs(User::factory()->create())->get('/settings/danger-zone')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Settings/DangerZone')
            ->where('graceDays', config('platform.auth.deletion_grace_days'))
            ->where('canRequestDeletion', true)->where('meta.title', 'Danger zone')
            ->missing('email')->missing('password')->missing('userId'));

    $this->actingAs(User::factory()->suspended()->create())->get('/settings/danger-zone')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canRequestDeletion', false));
});

it('requests deletion only after explicit confirmation and hides the public profile', function () {
    $user = User::factory()->create(['username' => 'chief']);
    $this->actingAs($user)
        ->from('/settings/danger-zone')->delete('/settings/danger-zone', ['current_password' => 'password'])->assertSessionHasErrors('confirmation');
    expect($user->refresh()->status)->toBe(UserStatus::Active);

    $this->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'password'])->assertRedirect('/login');
    $this->assertGuest();
    expect($user->refresh()->status)->toBe(UserStatus::PendingDeletion)
        ->and($user->deletion_requested_at?->equalTo(now()))->toBeTrue()
        ->and($user->deleted_at)->toBeNull();
    $this->get('/u/chief')->assertNotFound();
});

it('ends every browser session and remember cookie and cancels only on password sign-in', function () {
    $this->useDatabaseSessions();
    $user = User::factory()->create(['password' => 'password']);
    $other = $this->signInBrowser($user, remember: true);
    $mine = $this->signInBrowser($user, remember: true);
    $recaller = auth()->guard('web')->getRecallerName();
    $token = $user->refresh()->remember_token;
    $response = $this->browser($mine)->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'password']);
    $response->assertRedirect('/login')->assertCookieExpired($recaller)->assertCookieExpired('remember_since');
    expect(DB::table('sessions')->where('user_id', $user->id)->count())->toBe(0)
        ->and($user->refresh()->remember_token)->not->toBe($token);
    foreach ([$other, $mine] as $cookies) {
        $this->browser($cookies)->get('/settings/profile')->assertRedirect('/login');
        expect($user->refresh()->status)->toBe(UserStatus::PendingDeletion);
    }

    $this->browser([])->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
    expect($user->refresh()->status)->toBe(UserStatus::PendingDeletion);
    $this->browser([])->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirect('/');
    expect($user->refresh()->status)->toBe(UserStatus::Active)->and($user->deletion_requested_at)->toBeNull();
});

it('preserves a restriction until its expiry when cancellation restores the account', function (bool $expired) {
    $user = User::factory()->restricted(now()->addDay(), 'Spam')->create(['password' => 'password']);
    $this->actingAs($user)
        ->delete('/settings/danger-zone', ['confirmation' => true, 'current_password' => 'password']);
    if ($expired) {
        $this->travel(2)->days();
    }
    $account = app(AuthenticationService::class)->attempt($user->email, 'password');
    expect($account?->status)->toBe($expired ? UserStatus::Active : UserStatus::Restricted)
        ->and($account?->status_reason)->toBe($expired ? null : 'Spam')
        ->and($account?->deletion_previous_status)->toBeNull();
})->with([false, true]);

it('anonymises at the configured boundary once and keeps the retained identity', function () {
    Queue::fake();
    $days = (int) config('platform.auth.deletion_grace_days');
    $due = User::factory()->pendingDeletion(now()->subDays($days))->withPendingEmail('pending@example.com')
        ->create(['username' => 'chief', 'email' => 'chief@example.com', 'last_login_at' => now(), 'last_login_ip_hash' => 'hash']);
    $early = User::factory()->pendingDeletion(now()->subDays($days)->addSecond())->create();

    $this->artisan('platform:anonymize-deleted --dry-run')->expectsOutputToContain('Would anonymise 1 accounts.')->assertSuccessful();
    expect($due->refresh()->password)->not->toBeNull()->and(AuditLog::query()->count())->toBe(0);
    $this->artisan('platform:anonymize-deleted')->expectsOutputToContain('Anonymised 1 accounts.')->assertSuccessful();
    $this->artisan('platform:anonymize-deleted')->expectsOutputToContain('Anonymised 0 accounts.')->assertSuccessful();

    $tombstone = User::withTrashed()->findOrFail($due->id);
    expect($tombstone->username)->toBe('deleted_user_'.$due->ulid)
        ->and($tombstone->email)->not->toBe('chief@example.com')
        ->and($tombstone->password)->toBeNull()->and($tombstone->remember_token)->toBeNull()
        ->and($tombstone->pending_email)->toBeNull()->and($tombstone->email_verified_at)->toBeNull()
        ->and($tombstone->last_login_at)->toBeNull()->and($tombstone->last_login_ip_hash)->toBeNull()
        ->and($tombstone->status)->toBe(UserStatus::Banned)->and($tombstone->deleted_at)->not->toBeNull()
        ->and($early->refresh()->status)->toBe(UserStatus::PendingDeletion);
    expect(UsernameHistory::query()->where('username', 'CHIEF')->where('reserved_forever', true)->exists())->toBeTrue();
    $audit = AuditLog::query()->sole();
    expect($audit->action)->toBe(AuditAction::UserAnonymised)
        ->and(json_encode([$audit->before, $audit->after, $audit->context]))->not->toContain('chief', 'pending@example.com');
    $this->get('/u/chief')->assertNotFound();
    expect(User::factory()->create(['email' => 'chief@example.com'])->id)->not->toBe($due->id);
});

it('clears Phase 1 data and purges owned media while retaining quarantine and other accounts', function () {
    Queue::fake();
    Storage::fake(config('media.disk'));
    $user = User::factory()->pendingDeletion(now()->subDays((int) config('platform.auth.deletion_grace_days')))
        ->withProfileData(['display_name' => 'Private name', 'bio' => 'Private bio', 'languages' => ['en'], 'country_code' => 'PH', 'timezone' => 'Asia/Manila', 'socials' => ['discord' => 'chief']])
        ->withPrivacy(['profile_visibility' => 'private', 'show_clan' => false])->create();
    $profile = Profile::query()->where('user_id', $user->id)->firstOrFail();
    $avatar = Media::factory()->collection(MediaCollection::Avatar)->ready()->create(['user_id' => $user->id, 'attachable_type' => 'profile', 'attachable_id' => $profile->id]);
    $profile->forceFill(['avatar_media_id' => $avatar->id])->save();
    $orphan = Media::factory()->processing()->create(['user_id' => $user->id]);
    $quarantine = Media::factory()->quarantined()->create(['user_id' => $user->id]);
    $other = Media::factory()->ready()->create();
    foreach ([$avatar, $orphan, $quarantine, $other] as $media) {
        Storage::disk($media->disk)->put($media->path, 'bytes');
    }
    UserStats::query()->whereKey($user->id)->update(['bases_published' => 5]);
    app(Notifier::class)->send($user, new InAppMessageData(NotificationType::PasswordChanged));
    Cache::put(Notifier::unreadCacheKey($user->id), 1);
    Cache::put(CacheInvalidator::profileKey($user->username), ['private' => 'cached']);
    Cache::put(CacheInvalidator::privacyKey($user->id), ['private' => 'cached']);
    DB::table('password_reset_tokens')->insert(['email' => $user->email, 'token' => 'reset', 'created_at' => now()]);

    expect(app(AccountDeletionService::class)->anonymise($user->id))->toBeTrue();
    $profile->refresh();
    expect($profile->display_name)->toBeNull()->and($profile->bio)->toBeNull()->and($profile->avatar_media_id)->toBeNull()
        ->and($profile->languages)->toBe([])->and($profile->socials)->toBe([])->and($profile->timezone)->toBeNull()
        ->and(PrivacySettings::query()->findOrFail($user->id)->show_clan)->toBeTrue()
        ->and(UserStats::query()->findOrFail($user->id)->bases_published)->toBe(0);
    $this->assertDatabaseCount('notifications', 0);
    $this->assertDatabaseCount('password_reset_tokens', 0);
    expect(Cache::has(CacheInvalidator::profileKey($user->username)))->toBeFalse()
        ->and(Cache::has(CacheInvalidator::privacyKey($user->id)))->toBeFalse()
        ->and(Cache::has(Notifier::unreadCacheKey($user->id)))->toBeFalse();
    Queue::assertPushed(DeleteMediaObjectsJob::class);
    expect($avatar->refresh()->status)->toBe(MediaStatus::Deleting)->and($orphan->refresh()->status)->toBe(MediaStatus::Deleting)
        ->and($quarantine->refresh()->status)->toBe(MediaStatus::Quarantined)->and($other->refresh()->status)->toBe(MediaStatus::Ready);
    app(MediaLifecycleService::class)->deleteMedia([$avatar->id, $orphan->id]);
    Storage::disk($avatar->disk)->assertMissing([$avatar->path, $orphan->path]);
    Storage::disk($avatar->disk)->assertExists([$quarantine->path, $other->path]);
    expect(app(Notifier::class)->send($user, new InAppMessageData(NotificationType::PasswordChanged)))->toBeNull();
    $this->assertDatabaseCount('notifications', 0);
});

it('rechecks a selected due account after sign-in has cancelled deletion', function () {
    $user = User::factory()->pendingDeletion(now()->subDays((int) config('platform.auth.deletion_grace_days')))->create();
    app(AuthenticationService::class)->attempt($user->email, 'password');
    expect(app(AccountDeletionService::class)->anonymise($user->id))->toBeFalse();
    $this->assertDatabaseCount('audit_logs', 0);
    expect($user->refresh()->status)->toBe(UserStatus::Active);
});

it('keeps sanction records and audit entries when either their subject or actor is anonymised', function () {
    $actor = User::factory()->admin()->pendingDeletion(now()->subDays((int) config('platform.auth.deletion_grace_days')))->create();
    $actor->forceFill(['status' => UserStatus::Active])->save();
    $subject = User::factory()->create();
    $sanctions = app(SanctionService::class);
    $sanction = $sanctions->suspend($actor, $subject, new ApplySanctionData(ReasonCode::Harassment, 'Harassment', 'Retain this record', 1));
    $sanctions->lift($actor, $subject, 'Lifted after review');
    $retained = AuditLog::query()->get()->map->getAttributes()->all();
    $actor->forceFill(['status' => UserStatus::PendingDeletion])->save();
    $subject->forceFill(['status' => UserStatus::PendingDeletion, 'deletion_requested_at' => now()->subDays((int) config('platform.auth.deletion_grace_days'))])->save();

    expect(app(AccountDeletionService::class)->anonymiseDue())->toBe(2);
    expect(UserSanction::query()->findOrFail($sanction->id)->issued_by)->toBe($actor->id)
        ->and(ModerationAction::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', '!=', AuditAction::UserAnonymised)->get()->map->getAttributes()->all())->toBe($retained);
});

it('schedules the command at 04:00 with overlap and single-server protection', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'platform:anonymize-deleted'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe('0 4 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()->and($event->onOneServer)->toBeTrue();
    expect(config('platform.auth.deletion_grace_days'))->toBe(30);
});

it('anonymises separate accounts that reused the same email without a tombstone collision', function () {
    Queue::fake();
    $first = User::factory()->pendingDeletion(now()->subDays((int) config('platform.auth.deletion_grace_days')))->create(['email' => 'reused@example.com']);
    expect(app(AccountDeletionService::class)->anonymise($first->id))->toBeTrue();
    $second = User::factory()->pendingDeletion(now()->subDays((int) config('platform.auth.deletion_grace_days')))->create(['email' => 'reused@example.com']);
    expect(app(AccountDeletionService::class)->anonymise($second->id))->toBeTrue();
    expect(User::withTrashed()->findOrFail($first->id)->email)->not->toBe(User::withTrashed()->findOrFail($second->id)->email);
    expect(AuditLog::query()->where('action', AuditAction::UserAnonymised)->count())->toBe(2);
});

it('refuses stale password-reset users and completion after anonymisation', function () {
    $this->fakeHibp();
    Queue::fake();
    $stale = User::factory()->pendingDeletion(now()->subDays((int) config('platform.auth.deletion_grace_days')))->create();
    $email = $stale->email;
    expect(app(AccountDeletionService::class)->anonymise($stale->id))->toBeTrue();
    expect(fn () => app(PasswordResetService::class)->reset($stale, [
        'password' => 'brand-new-password', 'password_confirmation' => 'brand-new-password',
    ]))->toThrow(ValidationException::class);
    app(CompletePasswordReset::class)(auth()->guard('web'), $stale);
    app(PasswordResetService::class)->sendLink($email);
    $tombstone = User::withTrashed()->findOrFail($stale->id);
    expect($tombstone->password)->toBeNull()->and($tombstone->remember_token)->toBeNull();
    $this->assertDatabaseCount('password_reset_tokens', 0);
    Notification::assertNothingSent();
});
