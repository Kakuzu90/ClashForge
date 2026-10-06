<?php

use App\Domain\PlayerAccounts\Enums\SnapshotSource;
use App\Domain\PlayerAccounts\Models\CocAccount;
use App\Domain\PlayerAccounts\Models\CocAccountSnapshot;
use App\Domain\PlayerAccounts\Services\SnapshotCompaction;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;

// P2-21: specs/07 `coc_account_snapshots` retention. All for 90 days, then the last per UTC day, then
// the last per ISO week after a year; verification rows and each account's newest row always stay.

beforeEach(function () {
    // A Tuesday: the 90-day line is 2026-07-08 12:00, the one-year line 2025-10-06 12:00 (a Monday).
    Date::setTestNow('2026-10-06 12:00:00');
    $this->account = CocAccount::factory()->verified()->create();
});

function snap(CocAccount $account, string $at, SnapshotSource $source = SnapshotSource::Scheduled): string
{
    CocAccountSnapshot::factory()->create(['coc_account_id' => $account->id, 'captured_at' => $at, 'source' => $source]);

    return $at;
}

/**
 * @return list<string>
 */
function snapTimes(CocAccount $account): array
{
    return CocAccountSnapshot::query()->where('coc_account_id', $account->id)->orderBy('captured_at')
        ->pluck('captured_at')->map(fn ($at) => $at->format('Y-m-d H:i:s'))->all();
}

it('keeps everything recent, the last row of each old day, and the last of each week past a year', function () {
    $kept = [];
    // Recent: all stay.
    $kept[] = snap($this->account, '2026-09-26 08:00:00');
    $kept[] = snap($this->account, '2026-09-26 09:00:00');
    // An old day: only its last row.
    snap($this->account, '2026-06-20 01:00:00');
    snap($this->account, '2026-06-20 13:00:00');
    $kept[] = snap($this->account, '2026-06-20 23:30:00');
    // Midnight UTC splits days.
    $kept[] = snap($this->account, '2026-06-01 23:59:59');
    $kept[] = snap($this->account, '2026-06-02 00:00:00');
    // Past a year: the last row of each ISO week, Monday to Sunday.
    snap($this->account, '2025-06-02 10:00:00');
    snap($this->account, '2025-06-04 10:00:00');
    $kept[] = snap($this->account, '2025-06-08 23:59:59');
    $kept[] = snap($this->account, '2025-06-09 00:00:00');

    expect(app(SnapshotCompaction::class)->run())->toBe(['accounts' => 1, 'deleted' => 4])
        ->and(snapTimes($this->account))->toBe(collect($kept)->sort()->values()->all());
});

it('puts a row on either side of each line in exactly one bucket', function () {
    // The 90-day line falls inside 2026-07-08: the rows after it stay, the part before keeps its last.
    snap($this->account, '2026-07-08 10:00:00');
    $before = snap($this->account, '2026-07-08 11:00:00');
    $after = snap($this->account, '2026-07-08 13:00:00');
    // The one-year line falls inside the week of Monday 2025-10-06: the part before keeps its last.
    snap($this->account, '2025-10-06 08:00:00');
    $weekly = snap($this->account, '2025-10-06 09:00:00');
    $daily = snap($this->account, '2025-10-06 13:00:00');

    expect(app(SnapshotCompaction::class)->run()['deleted'])->toBe(2)
        ->and(snapTimes($this->account))->toBe([$weekly, $daily, $before, $after]);
});

it('always keeps verification snapshots', function () {
    $verified = snap($this->account, '2026-06-20 05:00:00', SnapshotSource::Verification);
    snap($this->account, '2026-06-20 10:00:00');
    $last = snap($this->account, '2026-06-20 20:00:00');
    $verifiedLongAgo = snap($this->account, '2025-03-03 05:00:00', SnapshotSource::Verification);
    $lastOfWeek = snap($this->account, '2025-03-05 05:00:00', SnapshotSource::Manual);

    app(SnapshotCompaction::class)->run();

    expect(snapTimes($this->account))->toBe([$verifiedLongAgo, $lastOfWeek, $verified, $last]);
});

it("keeps an account's newest row however old it is", function () {
    snap($this->account, '2024-05-06 10:00:00');
    $newest = snap($this->account, '2024-05-07 10:00:00');

    app(SnapshotCompaction::class)->run();

    expect(snapTimes($this->account))->toBe([$newest]);
});

it('compacts each account on its own', function () {
    $other = CocAccount::factory()->verified()->create();
    snap($this->account, '2026-06-20 10:00:00');
    $mine = snap($this->account, '2026-06-20 11:00:00');
    $theirs = snap($other, '2026-06-20 09:00:00');

    app(SnapshotCompaction::class)->run();

    expect(snapTimes($this->account))->toBe([$mine])->and(snapTimes($other))->toBe([$theirs]);
});

it('deletes nothing on a second run or a dry run, and goes batch by batch', function () {
    config(['coc.snapshots.batch_size' => 1]);
    $accounts = [$this->account, CocAccount::factory()->verified()->create(), CocAccount::factory()->verified()->create()];
    foreach ($accounts as $account) {
        snap($account, '2026-06-20 10:00:00');
        snap($account, '2026-06-20 11:00:00');
    }

    expect(app(SnapshotCompaction::class)->run(dryRun: true))->toBe(['accounts' => 3, 'deleted' => 3])
        ->and(CocAccountSnapshot::query()->count())->toBe(6)
        ->and(app(SnapshotCompaction::class)->run())->toBe(['accounts' => 3, 'deleted' => 3])
        ->and(app(SnapshotCompaction::class)->run())->toBe(['accounts' => 3, 'deleted' => 0])
        ->and(CocAccountSnapshot::query()->count())->toBe(3);
});

it('runs as a command, with a dry run', function () {
    snap($this->account, '2026-06-20 10:00:00');
    snap($this->account, '2026-06-20 11:00:00');

    $this->artisan('coc:compact-snapshots --dry-run')->expectsOutputToContain('Would delete 1 snapshots across 1 accounts.')->assertSuccessful();
    expect(CocAccountSnapshot::query()->count())->toBe(2);

    $this->artisan('coc:compact-snapshots')->expectsOutputToContain('Deleted 1 snapshots across 1 accounts.')->assertSuccessful();
    expect(CocAccountSnapshot::query()->count())->toBe(1);
});

it('runs daily at 02:45 on one server, never twice at once', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains($e->command, 'coc:compact-snapshots'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('45 2 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('reads its limits from config', function () {
    expect(config('coc.snapshots'))->toBe(['keep_all_days' => 90, 'daily_until_days' => 365, 'batch_size' => 500]);
});
