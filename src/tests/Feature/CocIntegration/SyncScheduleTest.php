<?php

use App\Domain\CocIntegration\Enums\SyncResourceType;
use App\Domain\CocIntegration\Enums\SyncTier;
use App\Domain\CocIntegration\Models\SyncState;
use App\Domain\CocIntegration\Services\SyncSchedule;
use Illuminate\Support\Facades\Date;

// specs/09 §6: tier intervals, backoff, frozen retries and the stop, and the success rate.

beforeEach(function () {
    Date::setTestNow('2026-10-02 12:00:00');
    $this->schedule = app(SyncSchedule::class);
    $this->type = SyncResourceType::CocAccount;
});

function syncRow(int $id): SyncState
{
    return SyncState::query()->where('resource_type', SyncResourceType::CocAccount)->where('resource_id', $id)->firstOrFail();
}

it('lists due rows oldest first, up to the limit, skipping unscheduled and future ones', function () {
    SyncState::factory()->forAccount(1)->create(['next_due_at' => now()->subMinutes(5)]);
    SyncState::factory()->forAccount(2)->create(['next_due_at' => now()->subHour()]);
    SyncState::factory()->forAccount(3)->create(['next_due_at' => now()->addMinute()]);
    SyncState::factory()->forAccount(4)->create(['next_due_at' => null]);
    SyncState::factory()->forAccount(5)->create(['next_due_at' => now()]);
    SyncState::factory()->create(['resource_type' => SyncResourceType::Clan, 'resource_id' => 6, 'next_due_at' => now()->subDay()]);

    expect($this->schedule->due($this->type, 10))->toBe([2, 1, 5])
        ->and($this->schedule->due($this->type, 2))->toBe([2, 1])
        ->and($this->schedule->due($this->type, 0))->toBe([]);
});

it('schedules the next sync one tier interval after a success, and clears failures', function (SyncTier $tier) {
    SyncState::factory()->forAccount(1)->frozen(2)->create();

    $state = $this->schedule->recordSuccess($this->type, 1, $tier);

    expect($state->nextDueAt?->toIso8601String())->toBe(now()->addSeconds((int) config("coc.sync.tiers.{$tier->value}"))->toIso8601String())
        ->and(syncRow(1))
        ->tier->toBe($tier)
        ->consecutive_failures->toBe(0)
        ->frozen_attempts->toBe(0)
        ->last_success_at->toIso8601String()->toBe(now()->toIso8601String());
})->with([SyncTier::Hot, SyncTier::Warm, SyncTier::Cold]);

it('backs off exponentially, never past the tier interval', function () {
    $base = (int) config('coc.sync.backoff_base');
    config(['coc.sync.tiers.hot' => $base * 3]);
    SyncState::factory()->forAccount(1)->create(['tier' => SyncTier::Hot]);

    $delays = [];
    foreach (range(1, 3) as $_) {
        $delays[] = (int) now()->diffInSeconds($this->schedule->recordFailure($this->type, 1)->nextDueAt);
    }

    expect($delays)->toBe([$base, $base * 2, $base * 3])
        ->and(syncRow(1)->consecutive_failures)->toBe(3);
});

it('freezes after the failure line, or at once when asked', function () {
    $after = (int) config('coc.sync.frozen_after');
    SyncState::factory()->forAccount(1)->create(['consecutive_failures' => $after - 1]);
    SyncState::factory()->forAccount(2)->create();

    $frozen = $this->schedule->recordFailure($this->type, 1);
    $asked = $this->schedule->recordFailure($this->type, 2, freeze: true);

    expect($frozen->tier)->toBe(SyncTier::Frozen)
        ->and($frozen->nextDueAt?->toIso8601String())->toBe(now()->addSeconds((int) config('coc.sync.tiers.frozen'))->toIso8601String())
        ->and($asked->tier)->toBe(SyncTier::Frozen);
});

it('retries a frozen row weekly and stops it after the last retry', function () {
    $max = (int) config('coc.sync.frozen_max_attempts');
    SyncState::factory()->forAccount(1)->frozen()->create();

    foreach (range(1, $max - 1) as $_) {
        expect($this->schedule->recordFailure($this->type, 1)->stopped())->toBeFalse();
    }

    $last = $this->schedule->recordFailure($this->type, 1);

    expect($last->stopped())->toBeTrue()
        ->and(syncRow(1))->frozen_attempts->toBe($max)->next_due_at->toBeNull()
        ->and($this->schedule->stoppedCount($this->type))->toBe(1);

    // A later success revives it.
    $this->schedule->recordSuccess($this->type, 1, SyncTier::Cold);
    expect($this->schedule->stoppedCount($this->type))->toBe(0);
});

it('postpones without counting a failure, and drops the row of a resource no longer synced', function () {
    SyncState::factory()->forAccount(1)->create(['consecutive_failures' => 2]);
    SyncState::factory()->forAccount(2)->create();

    $this->schedule->postpone($this->type, 1, 120);
    $this->schedule->stop($this->type, 2);

    expect(syncRow(1))->consecutive_failures->toBe(2)->next_due_at->toIso8601String()->toBe(now()->addSeconds(120)->toIso8601String())
        ->and(SyncState::query()->where('resource_id', 2)->exists())->toBeFalse();
});

it('never counts a frozen row that was stopped for another reason as stopped syncing', function () {
    SyncState::factory()->forAccount(1)->frozen(1)->create();

    $this->schedule->stop($this->type, 1);

    expect($this->schedule->stoppedCount($this->type))->toBe(0);
});

it('claims the due rows it hands out, so the next run does not take them again', function () {
    SyncState::factory()->forAccount(1)->create(['next_due_at' => now()->subMinute()]);
    SyncState::factory()->forAccount(2)->create(['next_due_at' => now()->addHour()]);

    expect($this->schedule->claimDue($this->type, 10, 1800))->toBe([1])
        ->and(syncRow(1)->next_due_at?->toIso8601String())->toBe(now()->addSeconds(1800)->toIso8601String())
        ->and($this->schedule->claimDue($this->type, 10, 1800))->toBe([]);
});

it('starts a schedule fresh after a verification', function () {
    SyncState::factory()->forAccount(1)->stopped()->create();

    $this->schedule->start($this->type, 1, SyncTier::Hot);

    expect(syncRow(1))
        ->tier->toBe(SyncTier::Hot)
        ->frozen_attempts->toBe(0)
        ->next_due_at->toIso8601String()->toBe(now()->addSeconds((int) config('coc.sync.tiers.hot'))->toIso8601String());
});

it('computes the success rate over the window from each row\'s latest attempt', function () {
    $window = (int) config('coc.sync.success_window_minutes');
    SyncState::factory()->forAccount(1)->create(['last_attempt_at' => now()->subMinutes(5), 'last_success_at' => now()->subMinutes(5)]);
    SyncState::factory()->forAccount(2)->create(['last_attempt_at' => now()->subMinutes(10), 'last_success_at' => now()->subDay()]);
    SyncState::factory()->forAccount(3)->create(['last_attempt_at' => now()->subMinutes($window), 'last_success_at' => now()->subMinutes($window)]);
    SyncState::factory()->forAccount(4)->create(['last_attempt_at' => now()->subMinutes($window + 1), 'last_success_at' => null]);

    expect($this->schedule->rate($this->type))
        ->windowMinutes->toBe($window)
        ->attempts->toBe(3)
        ->successes->toBe(2)
        ->rate->toBe(0.667);
});

it('has no rate without attempts', function () {
    expect($this->schedule->rate($this->type))->attempts->toBe(0)->rate->toBeNull();
});
