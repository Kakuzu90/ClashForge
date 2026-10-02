<?php

use App\Domain\Auth\Notifications\PasswordChangedNotification;
use App\Domain\Auth\Services\AccountDeletionService;
use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Events\MediaRetriesExhausted;
use App\Domain\Notifications\Data\RenderedNotificationData;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Jobs\SendEmailNotificationJob;
use App\Domain\Notifications\Models\EmailDelivery;
use App\Domain\Notifications\Models\Notification;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Domain\Notifications\Notifications\NonSecurityEmail;
use App\Domain\Notifications\Services\EmailDeliveryService;
use App\Domain\Notifications\Support\UnsubscribeCapability;
use App\Models\User;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function () {
    Date::setTestNow('2026-10-01 12:00:00');
    Mail::fake();
    $this->recipient = User::factory()->create();
});

function mediaFailureJob(User $recipient, ?string $key = null): SendEmailNotificationJob
{
    return new SendEmailNotificationJob($recipient->id, NotificationType::MediaProcessingFailed, $key ?? (string) Str::ulid(), ['collection' => 'avatar']);
}

it('queues exhausted media mail on low alongside the existing in-app notice', function () {
    Queue::fake([SendEmailNotificationJob::class]);
    MediaRetriesExhausted::dispatch('01hzzzzzzzzzzzzzzzzzzzzzzz', $this->recipient->id, MediaCollection::Avatar);
    Queue::assertPushedOn('low', SendEmailNotificationJob::class, fn (SendEmailNotificationJob $job) => $job->userId === $this->recipient->id && $job->eventKey === '01hzzzzzzzzzzzzzzzzzzzzzzz');
    expect(Notification::query()->where('notifiable_id', $this->recipient->id)->where('type', 'media_processing_failed')->count())->toBe(1);
    Mail::assertNothingSent();
});

it('sends multipart mail with the signed unsubscribe header', function () {
    mediaFailureJob($this->recipient)->handle(app(EmailDeliveryService::class));
    Mail::assertSent(NonSecurityEmail::class, function (NonSecurityEmail $mail): bool {
        expect($mail->hasTo($this->recipient->email))->toBeTrue()
            ->and($mail->headers()->text['List-Unsubscribe'])->toBe('<'.$mail->unsubscribeUrl.'>')
            ->and($mail->notice->title)->toBe('An upload could not be processed');
        $mail->assertSeeInHtml('Upload it again.');
        $mail->assertSeeInText('Upload it again.');
        $mail->assertSeeInText('Unsubscribe from non-security emails');
        expect($mail->unsubscribeUrl)->toBe(UnsubscribeCapability::urlFor($this->recipient));

        return true;
    });
    expect(EmailDelivery::query()->where('user_id', $this->recipient->id)->count())->toBe(1);
});

it('does not resend a completed event even after the cache is cleared', function () {
    $job = mediaFailureJob($this->recipient);
    $job->handle(app(EmailDeliveryService::class));
    Cache::flush();
    $job->handle(app(EmailDeliveryService::class));
    Mail::assertSentCount(1);
    expect(EmailDelivery::query()->count())->toBe(1);
});

it('caps mail per recipient per UTC day and permits the next day', function () {
    $limit = (int) config('platform.notifications.email_per_day');
    for ($i = 0; $i < $limit + 2; $i++) {
        mediaFailureJob($this->recipient)->handle(app(EmailDeliveryService::class));
    }
    Mail::assertSentCount($limit);
    expect(EmailDelivery::query()->count())->toBe($limit);
    Date::setTestNow('2026-10-02 00:00:00');
    mediaFailureJob($this->recipient)->handle(app(EmailDeliveryService::class));
    Mail::assertSentCount($limit + 1);
});

it('keeps each recipients cap separate', function () {
    config(['platform.notifications.email_per_day' => 1]);
    mediaFailureJob($this->recipient)->handle(app(EmailDeliveryService::class));
    mediaFailureJob($this->recipient)->handle(app(EmailDeliveryService::class));
    $other = User::factory()->create();
    mediaFailureJob($other)->handle(app(EmailDeliveryService::class));
    Mail::assertSentCount(2);
});

it('restores the daily cap from receipts after cache eviction', function () {
    config(['platform.notifications.email_per_day' => 1]);
    mediaFailureJob($this->recipient)->handle(app(EmailDeliveryService::class));
    Cache::flush();
    mediaFailureJob($this->recipient)->handle(app(EmailDeliveryService::class));
    Mail::assertSentCount(1);
});

it('rechecks category and global opt-outs after enqueueing', function (bool $global) {
    $job = mediaFailureJob($this->recipient);
    NotificationPreference::factory()->create(['user_id' => $this->recipient->id, 'non_security_email_enabled' => ! $global, 'channel_prefs' => ['bases' => ['in_app' => true, 'email' => $global]]]);
    $job->handle(app(EmailDeliveryService::class));
    Mail::assertNothingSent();
    expect(EmailDelivery::query()->count())->toBe(0);
})->with([true, false]);

it('skips missing and deleted recipients', function () {
    $job = mediaFailureJob($this->recipient);
    $this->recipient->delete();
    $job->handle(app(EmailDeliveryService::class));
    mediaFailureJob((new User)->forceFill(['id' => 999999]))->handle(app(EmailDeliveryService::class));
    Mail::assertNothingSent();
    expect(NotificationPreference::query()->count())->toBe(0)->and(EmailDelivery::query()->count())->toBe(0);
});

it('keeps security emails on high and outside both preferences and the daily cap', function () {
    NotificationFacade::fake();
    NotificationPreference::factory()->unsubscribed()->create(['user_id' => $this->recipient->id]);
    config(['platform.notifications.email_per_day' => 0]);
    $this->recipient->notify(new PasswordChangedNotification);
    NotificationFacade::assertSentTo($this->recipient, PasswordChangedNotification::class, fn (PasswordChangedNotification $notice) => $notice->queue === 'high');
    (new SendEmailNotificationJob($this->recipient->id, NotificationType::PasswordChanged, 'security'))->handle(app(EmailDeliveryService::class));
    Mail::assertNothingSent();
});

it('can retry a transport failure without claiming a completed receipt', function () {
    $job = mediaFailureJob($this->recipient);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Transport refused delivery'));
    expect(fn () => $job->handle(app(EmailDeliveryService::class)))->toThrow(RuntimeException::class);
    expect(EmailDelivery::query()->count())->toBe(0);
    Mail::swap(new MailManager($this->app));
    Mail::fake();
    $job->handle(app(EmailDeliveryService::class));
    Mail::assertSentCount(1);
    $job->handle(app(EmailDeliveryService::class));
    Mail::assertSentCount(1);
});

it('cleans preferences and completed receipts on anonymisation and skips delayed work', function () {
    mediaFailureJob($this->recipient)->handle(app(EmailDeliveryService::class));
    NotificationPreference::factory()->create(['user_id' => $this->recipient->id]);
    $late = mediaFailureJob($this->recipient);
    $this->recipient->forceFill(['status' => 'pending_deletion', 'deletion_requested_at' => Date::now()->subDays((int) config('platform.auth.deletion_grace_days'))])->save();
    expect(app(AccountDeletionService::class)->anonymise($this->recipient->id))->toBeTrue();
    $late->handle(app(EmailDeliveryService::class));
    Mail::assertSentCount(1);
    expect(NotificationPreference::query()->count())->toBe(0)->and(EmailDelivery::query()->count())->toBe(0);
});

it('uses the documented queue retry policy with a timeout below retry_after', function () {
    $job = mediaFailureJob($this->recipient);
    expect($job->queue)->toBe('low')->and($job->tries)->toBe(3)->and($job->backoff)->toBe([60, 300, 900])
        ->and($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'))
        ->and($job->afterCommit)->toBeTrue()
        ->and(config('platform.notifications.email_per_day'))->toBe(10)
        ->and(config('platform.notifications.email_counter_ttl'))->toBeGreaterThanOrEqual(86400);
});

it('labels the email button per notice, with a plain default', function () {
    $mail = fn (?string $label) => new NonSecurityEmail(new RenderedNotificationData('Title', 'Body', '/somewhere', $label), 'https://example.test/unsubscribe');

    $mail('View your account')->assertSeeInHtml('View your account');
    $mail(null)->assertSeeInHtml('Open Clash Commons');
});
