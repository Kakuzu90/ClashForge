<?php

use App\Domain\CocIntegration\Data\PlayerTag;
use App\Domain\Notifications\Data\UpdateEmailPreferencesData;
use App\Domain\Notifications\Enums\NotificationCategory;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Jobs\SendEmailNotificationJob;
use App\Domain\Notifications\Models\EmailDelivery;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Notifications\NonSecurityEmail;
use App\Domain\Notifications\Services\EmailPreferenceService;
use App\Domain\Notifications\Services\InAppChannel;
use App\Domain\PlayerAccounts\Enums\ClaimStatus;
use App\Domain\PlayerAccounts\Enums\VerificationMethod;
use App\Domain\PlayerAccounts\Events\CocAccountOwnershipTransferred;
use App\Domain\PlayerAccounts\Events\CocAccountVerified;
use App\Domain\PlayerAccounts\Listeners\SendOwnershipNotice;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountClaim;
use App\Domain\PlayerAccounts\Notifications\CocAccountTakenOverNotification;
use App\Domain\PlayerAccounts\Services\VerifyOwnershipService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Coc\InteractsWithCoc;

// specs/13 §8 and §9, specs/16 §2: "CoC account verified" (I + E) and the takeover notice (I + E*).

uses(InteractsWithCoc::class);

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    Mail::fake();
    $this->tag = PlayerTag::from('#2PQ8GRJC');
    $this->user = User::factory()->create();
    $this->holder = User::factory()->create();
    $this->fakeCoc()->acceptToken($this->tag, 'user-token');
    $this->fakeCoc()->acceptToken($this->tag, 'holder-token');
    // The token path for a tag someone else holds; with the user's own row it is a plain verify.
    $this->verifyAs = fn (User $user, string $token) => app(VerifyOwnershipService::class)->verifyTag($user, $this->tag, $token);
    $this->notices = fn (User $user, NotificationType $type) => Notification::query()
        ->where('notifiable_id', $user->id)->where('type', $type->value)->get();
});

it('tells the verifier in-app and by email, keyed by the succeeded claim', function () {
    ($this->verifyAs)($this->user, 'user-token');

    $account = CocAccount::query()->where('user_id', $this->user->id)->sole();
    $claim = CocAccountClaim::query()->where('user_id', $this->user->id)->where('status', ClaimStatus::Succeeded)->latest('id')->first();
    $notice = ($this->notices)($this->user, NotificationType::CocAccountVerified)->sole();

    expect($notice->data)->toBe(['params' => ['tag' => '#2PQ8GRJC', 'name' => $account->ign, 'account' => $account->ulid]])
        ->and(EmailDelivery::query()->where('user_id', $this->user->id)->sole()->event_key)->toBe((string) $claim->id);
    Mail::assertSent(NonSecurityEmail::class, function (NonSecurityEmail $mail) use ($account): bool {
        $mail->assertSeeInHtml('View your account');

        return $mail->hasTo($this->user->email)
            && $mail->notice->title === 'Your Clash of Clans account is verified'
            && $mail->notice->url === "/accounts/{$account->ulid}";
    });
});

it('queues the verified email on low through the preference-checked job', function () {
    Queue::fake([SendEmailNotificationJob::class]);

    ($this->verifyAs)($this->user, 'user-token');

    Queue::assertPushedOn('low', SendEmailNotificationJob::class, fn (SendEmailNotificationJob $job) => $job->userId === $this->user->id
        && $job->type === NotificationType::CocAccountVerified && $job->params === ['tag' => '#2PQ8GRJC', 'name' => CocAccount::query()->sole()->ign, 'account' => CocAccount::query()->sole()->ulid]);
});

it('sends the previous holder the takeover notice on mail and in-app', function () {
    NotificationFacade::fake();
    ($this->verifyAs)($this->holder, 'holder-token');
    ($this->verifyAs)($this->user, 'user-token');

    NotificationFacade::assertSentTo($this->holder, CocAccountTakenOverNotification::class, function (CocAccountTakenOverNotification $notice, array $channels) {
        return $channels === ['mail', InAppChannel::class]
            && $notice->params === ['tag' => '#2PQ8GRJC', 'method' => 'api_token'];
    });
    NotificationFacade::assertNotSentTo($this->user, CocAccountTakenOverNotification::class);
});

it('notifies both users when two valid tokens arrive seconds apart (specs/13 §9)', function () {
    ($this->verifyAs)($this->holder, 'holder-token');
    Date::setTestNow('2026-10-02 12:00:05');
    ($this->verifyAs)($this->user, 'user-token');

    expect(($this->notices)($this->holder, NotificationType::CocAccountVerified))->toHaveCount(1)
        ->and(($this->notices)($this->holder, NotificationType::CocAccountTakenOver))->toHaveCount(1)
        ->and(($this->notices)($this->user, NotificationType::CocAccountVerified))->toHaveCount(1)
        ->and(($this->notices)($this->user, NotificationType::CocAccountTakenOver))->toHaveCount(0);
});

it('stops the verified email but never the takeover email when Accounts email is off (specs/16 §2)', function () {
    foreach ([$this->user, $this->holder] as $person) {
        app(EmailPreferenceService::class)->update($person, new UpdateEmailPreferencesData(true, [NotificationCategory::Ownership->value => false]));
    }
    ($this->verifyAs)($this->holder, 'holder-token');
    Mail::assertNothingSent();

    NotificationFacade::fake();
    ($this->verifyAs)($this->user, 'user-token');

    NotificationFacade::assertSentTo($this->holder, CocAccountTakenOverNotification::class, fn ($notice, array $channels) => in_array('mail', $channels, true));
    expect((new CocAccountTakenOverNotification(['tag' => '#2PQ8GRJC', 'method' => 'api_token']))->via($this->holder))->toContain('mail');
});

it('delivers the takeover email with all non-security email off', function () {
    app(EmailPreferenceService::class)->update($this->holder, new UpdateEmailPreferencesData(false, [NotificationCategory::Ownership->value => false]));
    ($this->verifyAs)($this->holder, 'holder-token');
    Mail::swap(new MailManager(app()));

    ($this->verifyAs)($this->user, 'user-token');

    $sent = app('mailer')->getSymfonyTransport()->messages()->map(fn ($message) => $message->getOriginalMessage());
    expect($sent->filter(fn ($email) => $email->getTo()[0]->getAddress() === $this->holder->email)->map->getSubject()->values()->all())
        ->toBe(['Someone else verified one of your accounts']);
});

it('caps the verified email per day but writes every in-app notice (specs/16 §4)', function () {
    config(['platform.notifications.email_per_day' => 1]);
    $second = PlayerTag::from('#GRJ0P8UV');
    $this->fakeCoc()->acceptToken($second, 'second-token');

    ($this->verifyAs)($this->user, 'user-token');
    app(VerifyOwnershipService::class)->verifyTag($this->user, $second, 'second-token');

    expect(($this->notices)($this->user, NotificationType::CocAccountVerified))->toHaveCount(2)
        ->and(EmailDelivery::query()->where('user_id', $this->user->id)->count())->toBe(1);
    Mail::assertSentCount(1);
});

it('still writes the verified notice in-app with all non-security email off', function () {
    app(EmailPreferenceService::class)->update($this->user, new UpdateEmailPreferencesData(false, []));

    ($this->verifyAs)($this->user, 'user-token');

    expect(($this->notices)($this->user, NotificationType::CocAccountVerified))->toHaveCount(1);
    Mail::assertNothingSent();
});

it('emails each verification of the same tag, but a retried event once', function () {
    ($this->verifyAs)($this->user, 'user-token');
    ($this->verifyAs)($this->holder, 'holder-token');
    ($this->verifyAs)($this->user, 'user-token');
    Mail::assertSent(NonSecurityEmail::class, fn (NonSecurityEmail $mail) => $mail->hasTo($this->user->email));
    expect(EmailDelivery::query()->where('user_id', $this->user->id)->count())->toBe(2);

    $account = CocAccount::query()->where('user_id', $this->user->id)->sole();
    $claim = CocAccountClaim::query()->where('user_id', $this->user->id)->where('status', ClaimStatus::Succeeded)->latest('id')->first();
    app(SendOwnershipNotice::class)->handleVerified(new CocAccountVerified($account->id, $this->user->id, $claim->id));

    expect(EmailDelivery::query()->where('user_id', $this->user->id)->count())->toBe(2);
});

it('writes nothing for a deleted recipient', function () {
    NotificationFacade::fake();
    $account = CocAccount::factory()->for($this->user)->forTag('#2PQ8GRJC')->verified()->create();
    $this->holder->delete();
    $this->user->delete();

    app(SendOwnershipNotice::class)->handleTransferred(new CocAccountOwnershipTransferred($account->id, $this->holder->id, $this->user->id, VerificationMethod::ApiToken));
    app(SendOwnershipNotice::class)->handleVerified(new CocAccountVerified($account->id, $this->user->id, 1));

    NotificationFacade::assertNothingSent();
    expect(Notification::query()->count())->toBe(0);
    Mail::assertNothingSent();
});

it('sends nothing until the verification commits', function () {
    DB::beginTransaction();
    ($this->verifyAs)($this->user, 'user-token');
    expect(Notification::query()->count())->toBe(0);
    DB::commit();

    expect(($this->notices)($this->user, NotificationType::CocAccountVerified))->toHaveCount(1);
});

it('sends no second notice for a refused second verify', function () {
    ($this->verifyAs)($this->user, 'user-token');
    $ulid = CocAccount::query()->where('user_id', $this->user->id)->value('ulid');

    expect(fn () => app(VerifyOwnershipService::class)->verify($this->user, $ulid, 'user-token'))->toThrow(AuthorizationException::class)
        ->and(Notification::query()->count())->toBe(1);
});

it('links the verified notice to the account page, and keeps older notices without a link', function () {
    $ulid = CocAccount::factory()->create()->ulid;

    expect(NotificationType::CocAccountVerified->render(['tag' => '#2PQ8GRJC', 'name' => 'Chief', 'account' => $ulid])->url)->toBe("/accounts/{$ulid}")
        ->and(NotificationType::CocAccountVerified->render(['tag' => '#2PQ8GRJC', 'name' => 'Chief'])->url)->toBeNull()
        ->and(NotificationType::CocAccountVerified->render(['account' => '../admin'])->url)->toBeNull();
});
