<?php

use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Models\SyncState;
use App\Domain\CocIntegration\Services\CocApiStatus;
use App\Domain\PlayerAccounts\Enums\SyncOutcome;
use App\Domain\PlayerAccounts\Jobs\SyncCocAccountJob;
use App\Domain\PlayerAccounts\Services\AccountSyncService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Queue;

// specs/09 §6 scheduler shape, specs/20 §2–3.

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    Queue::fake();
});

function apiStatus(bool $available, int $budget): void
{
    test()->mock(CocApiStatus::class, function ($mock) use ($available, $budget) {
        $mock->shouldReceive('isAvailable')->andReturn($available);
        $mock->shouldReceive('backgroundBudgetRemaining')->andReturn($budget);
    });
}

it('queues one job per due account on the sync queue', function () {
    SyncState::factory()->forAccount(7)->create(['next_due_at' => now()->subMinute()]);
    SyncState::factory()->forAccount(8)->create(['next_due_at' => now()->addHour()]);

    $this->artisan('coc:sync-accounts')->expectsOutputToContain('Queued 1 account syncs')->assertSuccessful();

    Queue::assertPushedOn((string) config('coc.sync.queue'), SyncCocAccountJob::class, fn (SyncCocAccountJob $job) => $job->accountId === 7);
    Queue::assertPushed(SyncCocAccountJob::class, 1);
});

it('queues no more than the batch size or the background budget left', function (int $batch, int $budget, int $expected) {
    config(['coc.sync.batch_size' => $batch]);
    apiStatus(true, $budget);
    foreach (range(1, 5) as $id) {
        SyncState::factory()->forAccount($id)->create(['next_due_at' => now()->subMinutes($id)]);
    }

    $this->artisan('coc:sync-accounts')->assertSuccessful();

    Queue::assertPushed(SyncCocAccountJob::class, $expected);
})->with([
    'batch' => [2, 100, 2],
    'budget' => [100, 3, 3],
    'budget spent' => [100, 0, 0],
]);

it('does not queue an account again while its job is still waiting', function () {
    SyncState::factory()->forAccount(7)->create(['next_due_at' => now()->subMinute()]);

    $this->artisan('coc:sync-accounts');
    Date::setTestNow(now()->addMinutes(5));
    $this->artisan('coc:sync-accounts');

    Queue::assertPushed(SyncCocAccountJob::class, 1);

    // A job that never ran frees the account once the claim runs out.
    Date::setTestNow(now()->addSeconds((int) config('coc.sync.claim_seconds')));
    $this->artisan('coc:sync-accounts');
    Queue::assertPushed(SyncCocAccountJob::class, 2);
});

it('counts a job that ran out of tries against its account', function () {
    SyncState::factory()->forAccount(42)->create();

    (new SyncCocAccountJob(42))->failed(new RuntimeException('boom'));

    $row = SyncState::query()->where('resource_id', 42)->sole();
    expect($row->consecutive_failures)->toBe(1)
        ->and((int) now()->diffInSeconds($row->next_due_at))->toBe((int) config('coc.sync.backoff_base'));
});

it('queues the oldest due accounts first', function () {
    config(['coc.sync.batch_size' => 1]);
    SyncState::factory()->forAccount(1)->create(['next_due_at' => now()->subMinute()]);
    SyncState::factory()->forAccount(2)->create(['next_due_at' => now()->subHour()]);

    $this->artisan('coc:sync-accounts');

    Queue::assertPushed(SyncCocAccountJob::class, fn (SyncCocAccountJob $job) => $job->accountId === 2);
});

it('queues nothing while the circuit is open', function () {
    apiStatus(false, 100);
    SyncState::factory()->forAccount(1)->create(['next_due_at' => now()->subMinute()]);

    $this->artisan('coc:sync-accounts')->assertSuccessful();

    Queue::assertNothingPushed();
});

it('is unique per account and never overlaps itself', function () {
    $job = new SyncCocAccountJob(42);
    $middleware = $job->middleware()[0];

    expect($job->uniqueId())->toBe('42')
        ->and($job->uniqueFor)->toBe(60)
        ->and($job->queue)->toBe(config('coc.sync.queue'))
        ->and($job->timeout)->toBeLessThan(60)
        ->and($middleware)->toBeInstanceOf(WithoutOverlapping::class)
        ->and($middleware->key)->toBe('coc-account-sync:42');
});

it('runs the sync service for its account', function () {
    $this->mock(AccountSyncService::class)->shouldReceive('sync')->once()->with(42)->andReturn(SyncOutcome::Unchanged);

    (new SyncCocAccountJob(42))->handle(app(AccountSyncService::class));
});

it('runs every five minutes off the hour, one server, no overlap', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'coc:sync-accounts'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('2-59/5 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('reads its sync rules from config', function () {
    expect(SyncResourceType::CocAccount->value)->toBe('coc_account')
        ->and(config('coc.sync.tiers'))->toHaveKeys(['hot', 'warm', 'cold', 'frozen'])
        ->and(config('coc.sync.not_found_stale'))->toBeInt()
        ->and(config('coc.sync.success_alert'))->toBeFloat();
});
