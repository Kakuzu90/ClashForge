<?php

use App\Domain\Audit\Enums\AuditAction;
use App\Domain\Audit\Models\AuditLog;
use App\Domain\Auth\Jobs\SendVerificationLifecycleEmailJob;
use App\Domain\Auth\Models\UsernameHistory;
use App\Domain\Auth\Notifications\VerificationLifecycleEmail;
use App\Domain\Auth\Services\AccountDeletionService;
use App\Domain\Auth\Services\UnverifiedAccountLifecycleService;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Mail\MailManager;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Date::setTestNow('2026-10-01 12:00:00');
    $this->queueManager = Queue::getFacadeRoot();
    Queue::fake();
    Mail::fake();
});

it('queues a single reminder at the configured registration age and ignores last login', function () {
    $days = (int) config('platform.auth.unverified_reminder_days');
    $due = User::factory()->unverified()->create(['created_at' => now()->subDays($days), 'last_login_at' => now()]);
    $early = User::factory()->unverified()->create(['created_at' => now()->subDays($days)->addSecond()]);
    User::factory()->create(['created_at' => now()->subDays($days)]);
    $service = app(UnverifiedAccountLifecycleService::class);
    expect($service->process())->toBe(['reminders' => 1, 'warnings' => 0, 'purged' => 0]);
    Cache::flush();
    expect($service->process())->toBe(['reminders' => 0, 'warnings' => 0, 'purged' => 0]);
    Queue::assertPushedOn('high', SendVerificationLifecycleEmailJob::class, fn ($job) => $job->userId === $due->id && ! $job->warning);
    Queue::assertPushed(SendVerificationLifecycleEmailJob::class, 1);
    expect($due->refresh()->verification_reminder_queued_at?->equalTo(now()))->toBeTrue()
        ->and($early->refresh()->verification_reminder_queued_at)->toBeNull();
    Mail::assertNothingSent();
});

it('queues only the final warning for an overdue account and keeps it until the grace period ends', function () {
    $account = User::factory()->unverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_purge_days') + 10)]);
    $service = app(UnverifiedAccountLifecycleService::class);
    expect($service->process())->toBe(['reminders' => 0, 'warnings' => 1, 'purged' => 0]);
    Queue::assertPushed(SendVerificationLifecycleEmailJob::class, fn ($job) => $job->warning);
    expect($account->refresh()->verification_reminder_queued_at)->toBeNull();
    $job = new SendVerificationLifecycleEmailJob($account->id, $service::emailBinding($account), true, (string) $account->verification_notice_key);
    $job->handle($service);
    $this->travel((int) config('platform.auth.unverified_warning_grace_days'))->days();
    $this->travel(-1)->seconds();
    expect($service->process()['purged'])->toBe(0);
    $this->travel(1)->seconds();
    expect($service->process()['purged'])->toBe(1);
    expect($service->process()['purged'])->toBe(0);
});

it('separates the warning boundary from the reminder window', function () {
    $days = (int) config('platform.auth.unverified_warning_days');
    $due = User::factory()->unverified()->create(['created_at' => now()->subDays($days)]);
    User::factory()->unverified()->create(['created_at' => now()->subDays($days)->addSecond(), 'verification_reminder_queued_at' => now()->subDays(10)]);
    expect(app(UnverifiedAccountLifecycleService::class)->process())->toBe(['reminders' => 0, 'warnings' => 1, 'purged' => 0]);
    Queue::assertPushed(SendVerificationLifecycleEmailJob::class, fn ($job) => $job->userId === $due->id && $job->warning);
});

it('counts dry-run actions without changing records or queuing mail', function () {
    User::factory()->unverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_reminder_days'))]);
    User::factory()->unverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_warning_days'))]);
    $due = User::factory()->warnedUnverified()->create();
    $this->artisan('auth:process-unverified --dry-run')->expectsOutputToContain('Would process: 1 reminders, 1 warnings, 1 purged accounts.')->assertSuccessful();
    expect(User::query()->whereNotNull('verification_reminder_queued_at')->count())->toBe(0)
        ->and(User::query()->whereNotNull('verification_warning_queued_at')->count())->toBe(1)
        ->and($due->refresh()->deleted_at)->toBeNull();
    Queue::assertNothingPushed();
    Mail::assertNothingSent();
    $this->assertDatabaseCount('audit_logs', 0);
});

it('renders HTML and text notices with a fresh signed verification link and UTC deadline', function (bool $warning) {
    $account = User::factory()->unverified()->create([
        'created_at' => now()->subDays((int) config('platform.auth.unverified_warning_days')),
        $warning ? 'verification_warning_queued_at' : 'verification_reminder_queued_at' => now(),
    ]);
    config(['platform.notifications.email_per_day' => 0]);
    NotificationPreference::factory()->unsubscribed()->create(['user_id' => $account->id]);
    $job = new SendVerificationLifecycleEmailJob($account->id, UnverifiedAccountLifecycleService::emailBinding($account), $warning, (string) $account->verification_notice_key);
    $this->travel(2)->hours();
    $job->handle(app(UnverifiedAccountLifecycleService::class));
    $job->handle(app(UnverifiedAccountLifecycleService::class));
    Mail::assertSentCount(1);
    Mail::assertSent(VerificationLifecycleEmail::class, function ($mail) use ($account, $warning) {
        expect($mail->hasTo($account->email))->toBeTrue()->and($mail->warning)->toBe($warning)
            ->and($mail->deadline)->toContain('UTC')->and($mail->verificationUrl)->toContain('/email/verify/'.$account->ulid.'/');
        $mail->assertSeeInHtml('Confirm your email');
        $mail->assertSeeInText($mail->deadline);
        $mail->assertSeeInText('Your username stays reserved');
        $this->get($mail->verificationUrl)->assertOk();

        return true;
    });
    expect($account->refresh()->getAttribute($warning ? 'verification_warning_sent_at' : 'verification_reminder_sent_at'))->not->toBeNull();
})->with([false, true]);

it('does not purge after a queued warning failed or before a delayed send has its full grace period', function () {
    $account = User::factory()->warnedUnverified()->create(['verification_warning_sent_at' => null]);
    $service = app(UnverifiedAccountLifecycleService::class);
    expect($service->process()['purged'])->toBe(0);
    $job = new SendVerificationLifecycleEmailJob($account->id, $service::emailBinding($account), true, (string) $account->verification_notice_key);
    Mail::shouldReceive('to')->once()->andThrow(new RuntimeException('Transport unavailable'));
    expect(fn () => $job->handle($service))->toThrow(RuntimeException::class);
    expect($account->refresh()->verification_warning_sent_at)->toBeNull();
    Mail::swap(new MailManager($this->app));
    Mail::fake();
    $job->handle($service);
    expect($service->process()['purged'])->toBe(0);
    Mail::assertSent(VerificationLifecycleEmail::class, fn ($mail) => $mail->deadline === now()->addDays((int) config('platform.auth.unverified_warning_grace_days'))->format('j F Y, H:i').' UTC');
});

it('commits the database queue row and lifecycle marker atomically', function () {
    Queue::swap($this->queueManager);
    $account = User::factory()->unverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_reminder_days'))]);
    DB::beginTransaction();
    try {
        app(UnverifiedAccountLifecycleService::class)->process();
        expect(DB::table('jobs')->where('queue', 'high')->count())->toBe(1)
            ->and($account->refresh()->verification_reminder_queued_at)->not->toBeNull();
        $payload = DB::table('jobs')->where('queue', 'high')->value('payload');
        expect($payload)->not->toContain($account->email, $account->username, $account->password);
    } finally {
        DB::rollBack();
    }
    expect(DB::table('jobs')->where('queue', 'high')->count())->toBe(0)
        ->and($account->refresh()->verification_reminder_queued_at)->toBeNull();
});

it('rolls a marker back when queue insertion fails', function () {
    $account = User::factory()->unverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_reminder_days'))]);
    Bus::shouldReceive('dispatch')->once()->andThrow(new RuntimeException('Queue unavailable'));
    expect(fn () => app(UnverifiedAccountLifecycleService::class)->process())->toThrow(RuntimeException::class);
    expect($account->refresh()->verification_reminder_queued_at)->toBeNull();
});

it('refuses a separate queue database connection before changing any progress', function () {
    $account = User::factory()->unverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_reminder_days'))]);
    config(['queue.connections.database.connection' => 'separate_queue_database']);
    expect(fn () => app(UnverifiedAccountLifecycleService::class)->process())->toThrow(LogicException::class);
    expect($account->refresh()->verification_reminder_queued_at)->toBeNull();
    Queue::assertNothingPushed();
});

it('anonymises at the exact age boundary and preserves the existing cleanup contract', function () {
    $account = User::factory()->warnedUnverified()->withPendingEmail('pending@example.com')->withProfileData(['bio' => 'Private'])->create(['username' => 'never_chief', 'email' => 'never@example.com']);
    $early = User::factory()->warnedUnverified()->create(['created_at' => now()->subDays((int) config('platform.auth.unverified_purge_days'))->addSecond()]);
    DB::table('password_reset_tokens')->insert(['email' => $account->email, 'token' => 'private-reset', 'created_at' => now()]);
    $this->artisan('auth:process-unverified')->expectsOutputToContain('0 reminders, 0 warnings, 1 purged accounts.')->assertSuccessful();
    $deleted = User::withTrashed()->findOrFail($account->id);
    expect($deleted->password)->toBeNull()->and($deleted->remember_token)->toBeNull()
        ->and($deleted->pending_email)->toBeNull()->and($deleted->profile?->bio)->toBeNull()
        ->and($deleted->verification_warning_queued_at)->toBeNull()->and($deleted->verification_warning_sent_at)->toBeNull()
        ->and($early->refresh()->deleted_at)->toBeNull()
        ->and(UsernameHistory::query()->where('username', 'never_chief')->sole()->reserved_forever)->toBeTrue();
    $this->assertDatabaseCount('password_reset_tokens', 0);
    expect(AuditLog::query()->sole()->action)->toBe(AuditAction::UserAnonymised)
        ->and(AuditLog::query()->sole()->context)->toMatchArray(['command' => 'auth:process-unverified']);
    expect(app(AccountDeletionService::class)->purgeUnverified($account->id))->toBeFalse();
    $this->get('/u/never_chief')->assertNotFound();
    expect(User::factory()->create(['email' => 'never@example.com'])->id)->not->toBe($account->id);
});

it('processes multiple chunks and reports an empty run as successful', function () {
    config(['platform.auth.unverified_batch_size' => 2]);
    User::factory()->warnedUnverified()->count(5)->create();
    $this->artisan('auth:process-unverified')->expectsOutputToContain('5 purged accounts.')->assertSuccessful();
    $this->artisan('auth:process-unverified')->expectsOutputToContain('0 reminders, 0 warnings, 0 purged accounts.')->assertSuccessful();
    $this->assertDatabaseCount('audit_logs', 5);
});

it('uses the configured protected daily schedule and high queue retry policy', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'auth:process-unverified'));
    [$hour, $minute] = explode(':', config('platform.auth.unverified_schedule_time'));
    expect($event)->not->toBeNull()->and($event->expression)->toBe((int) $minute.' '.(int) $hour.' * * *')
        ->and($event->withoutOverlapping)->toBeTrue()->and($event->onOneServer)->toBeTrue()->and($event->runInBackground)->toBeTrue();
    $job = new SendVerificationLifecycleEmailJob(1, 'binding', false, 'dispatch-key');
    expect($job->queue)->toBe('high')->and($job->connection)->toBe('database')->and($job->tries)->toBe(3)
        ->and($job->backoff)->toBe([10, 30, 60])->and($job->timeout)->toBeLessThan(config('queue.connections.database.retry_after'))
        ->and(config('platform.auth.unverified_reminder_days'))->toBe(3)->and(config('platform.auth.unverified_warning_days'))->toBe(27)
        ->and(config('platform.auth.unverified_purge_days'))->toBe(30)->and(config('platform.auth.unverified_warning_grace_days'))->toBe(3);
});
