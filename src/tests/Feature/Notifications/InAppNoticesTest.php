<?php

use App\Domain\Auth\Events\PasswordChanged;
use App\Domain\Auth\Events\UnrecognisedDeviceSignedIn;
use App\Domain\Auth\Services\PasswordChangeService;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Events\MediaRetriesExhausted;
use App\Domain\Moderation\Data\ApplySanctionData;
use App\Domain\Moderation\Enums\ReasonCode;
use App\Domain\Moderation\Enums\SanctionType;
use App\Domain\Moderation\Events\SanctionApplied;
use App\Domain\Moderation\Listeners\SendSanctionNotice;
use App\Domain\Moderation\Notifications\AccountBannedNotification;
use App\Domain\Moderation\Notifications\AccountSuspendedNotification;
use App\Domain\Moderation\Notifications\SanctionEndedNotification;
use App\Domain\Moderation\Services\SanctionService;
use App\Domain\Notifications\Listeners\WriteInAppNotice;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Queries\NotificationReadModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Validation\ValidationException;
use Tests\Support\Auth\InteractsWithBrowsers;

// specs/16 §2: the Phase 1 events land in the notification centre. Their emails are unchanged.

uses(InteractsWithBrowsers::class);

beforeEach(function () {
    Date::setTestNow('2026-10-01 12:00:00');
    $this->user = User::factory()->create(['password' => 'password']);
});

/**
 * @return list<array{type: string, title: string, body: string, url: string|null}>
 */
function inAppRows(User $user): array
{
    return Notification::query()->where('notifiable_id', $user->id)->orderBy('created_at')->orderBy('id')->get()
        ->map(fn (Notification $n) => ['type' => $n->type, ...NotificationReadModel::render($n)->only('title', 'body', 'url')->toArray()])
        ->all();
}

it('announces a password change from settings after it is saved', function () {
    Event::fake([PasswordChanged::class]);
    NotificationFacade::fake();

    app(PasswordChangeService::class)->change($this->user, 'password', 'a-brand-new-passphrase', null);

    Event::assertDispatched(PasswordChanged::class, fn (PasswordChanged $event) => $event->userId === $this->user->id);
});

it('does not announce a refused password change', function () {
    Event::fake([PasswordChanged::class]);

    expect(fn () => app(PasswordChangeService::class)->change($this->user, 'wrong', 'a-brand-new-passphrase', null))->toThrow(ValidationException::class);

    Event::assertNotDispatched(PasswordChanged::class);
});

it('announces a sign-in from an unrecognised browser only', function () {
    Event::fake([UnrecognisedDeviceSignedIn::class]);
    NotificationFacade::fake();
    // Signed in before: an account's very first sign-in is not a new device.
    $this->user->forceFill(['last_login_at' => now()->subDay()])->save();

    $browser = $this->signInBrowser($this->user, ['User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64; rv:130.0) Gecko/20100101 Firefox/130.0']);

    Event::assertDispatched(UnrecognisedDeviceSignedIn::class, fn (UnrecognisedDeviceSignedIn $event) => $event->userId === $this->user->id && $event->device !== '');

    $this->browser($browser)->post('/logout');
    $this->signInBrowser($this->user, cookies: $browser);

    Event::assertDispatchedTimes(UnrecognisedDeviceSignedIn::class, 1);
});

it('writes the password-changed notice', function () {
    PasswordChanged::dispatch($this->user->id);

    expect(inAppRows($this->user))->toBe([[
        'type' => 'password_changed',
        'title' => 'Your password was changed',
        'body' => 'Every other device was signed out. If you did not change it, reset your password now.',
        'url' => '/settings/security',
    ]]);
});

it('writes the new-device notice with the device and country', function () {
    UnrecognisedDeviceSignedIn::dispatch($this->user->id, 'Safari on iPhone', null);

    expect(inAppRows($this->user)[0])->toMatchArray([
        'type' => 'new_device_sign_in',
        'body' => 'From a device we have not seen before: Safari on iPhone. If it was not you, sign that device out and change your password.',
        'url' => '/settings/security',
    ]);
});

it('writes the media-failed notice, linking to the avatar setting for an avatar', function (MediaCollection $collection, string $what, ?string $url) {
    MediaRetriesExhausted::dispatch('01hzzzzzzzzzzzzzzzzzzzzzzz', $this->user->id, $collection);

    expect(inAppRows($this->user)[0])->toMatchArray([
        'type' => 'media_processing_failed',
        'body' => "Your {$what} failed to process after several tries. Upload it again.",
        'url' => $url,
    ]);
})->with([
    'avatar' => [MediaCollection::Avatar, 'avatar', '/settings/profile'],
    'base screenshot' => [MediaCollection::BaseScreenshot, 'base screenshot', null],
]);

it('writes nothing for an account that no longer exists', function () {
    $this->user->delete();

    PasswordChanged::dispatch($this->user->id);
    MediaRetriesExhausted::dispatch('01hzzzzzzzzzzzzzzzzzzzzzzz', $this->user->id, MediaCollection::Avatar);

    expect(Notification::query()->count())->toBe(0);
});

it('queues the listener on high', function () {
    expect(app(WriteInAppNotice::class))->toBeInstanceOf(ShouldQueue::class)
        ->and(app(WriteInAppNotice::class)->queue)->toBe('high');
});

it('adds the in-app copy to the sanction notices next to their email', function () {
    $this->user->notify(new AccountSuspendedNotification('Spam links in comments', CarbonImmutable::parse('2026-10-08 12:00:00')));
    $this->user->notify(new AccountBannedNotification('Account trading'));
    $this->user->notify(new SanctionEndedNotification(SanctionType::Suspension, expired: true));

    expect(inAppRows($this->user))->toBe([
        [
            'type' => 'account_suspended',
            'title' => 'Your account is suspended',
            'body' => 'Until 8 October 2026 at 12:00 UTC. Reason: Spam links in comments',
            'url' => '/account/suspended',
        ],
        [
            'type' => 'account_banned',
            'title' => 'Your account is banned',
            'body' => 'Reason: Account trading',
            'url' => null,
        ],
        [
            'type' => 'sanction_ended',
            'title' => 'Your suspension is over',
            'body' => 'It has ended. Your account works as normal again.',
            'url' => null,
        ],
    ]);
});

it('writes the suspension and its end through the sanction service, as the account holder sees them', function () {
    $admin = User::factory()->admin()->create();
    $sanctions = app(SanctionService::class);

    $sanctions->suspend($admin, $this->user, new ApplySanctionData(reasonCode: ReasonCode::Spam, publicReason: 'Spam links', internalNote: 'Forty links.', days: 7));
    $sanctions->lift($admin, $this->user->refresh(), 'Appeal accepted.');

    expect(array_column(inAppRows($this->user), 'type'))->toBe(['account_suspended', 'sanction_ended'])
        ->and(inAppRows($this->user)[0]['body'])->toBe('Until 8 October 2026 at 12:00 UTC. Reason: Spam links')
        ->and(inAppRows($this->user)[1]['body'])->toBe('It has been lifted. Your account works as normal again.');
});

it('writes nothing for a sanction lifted before its notice ran', function () {
    $admin = User::factory()->admin()->create();
    $sanctions = app(SanctionService::class);

    Event::fake([SanctionApplied::class]);
    $sanction = $sanctions->suspend($admin, $this->user, new ApplySanctionData(reasonCode: ReasonCode::Spam, publicReason: 'Spam links', internalNote: 'Forty links.', days: 7));
    $sanction->forceFill(['lifted_at' => now(), 'lifted_by' => $admin->id])->save();

    app(SendSanctionNotice::class)->handleApplied(new SanctionApplied($this->user->id, $sanction->id));

    expect(Notification::query()->where('notifiable_id', $this->user->id)->where('type', 'account_suspended')->exists())->toBeFalse();
});
