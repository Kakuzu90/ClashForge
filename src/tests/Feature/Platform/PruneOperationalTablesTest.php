<?php

use App\Domain\CocIntegration\Models\CocApiRequest;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

// specs/20 §2–3: the nightly prune of operational tables (owner decision 2026-10-02).

beforeEach(function () {
    Date::setTestNow('2026-10-02 02:00:00');
    $now = Date::now();
    $keepDays = (int) config('coc.request_log.retention_days');

    $this->travelTo($now->subDays($keepDays)->subMinute());
    CocApiRequest::factory()->create();
    $this->travelTo($now->subDays($keepDays)->addMinute());
    CocApiRequest::factory()->cached()->create();
    $this->travelTo($now);

    $failedDays = (int) config('platform.prune.failed_jobs_days');
    foreach ([$failedDays + 1, $failedDays - 1] as $i => $days) {
        DB::table('failed_jobs')->insert([
            'uuid' => "00000000-0000-0000-0000-00000000000{$i}",
            'connection' => 'database', 'queue' => 'default', 'payload' => '{}', 'exception' => 'x',
            'failed_at' => $now->subDays($days),
        ]);
    }

    DB::table('cache')->insert([
        ['key' => 'expired', 'value' => 's:1:"x";', 'expiration' => $now->getTimestamp() - 1],
        ['key' => 'live', 'value' => 's:1:"x";', 'expiration' => $now->getTimestamp() + 60],
    ]);
    DB::table('cache_locks')->insert([
        ['key' => 'expired-lock', 'owner' => 'a', 'expiration' => $now->getTimestamp() - 1],
        ['key' => 'live-lock', 'owner' => 'b', 'expiration' => $now->getTimestamp() + 60],
    ]);

    $idle = (int) config('session.lifetime');
    $absolute = (int) config('platform.auth.absolute_session_days');
    DB::table('sessions')->insert([
        ['id' => 'idle-expired', 'payload' => '', 'last_activity' => $now->subMinutes($idle + 1)->getTimestamp(), 'created_at' => $now->subDays(1)],
        ['id' => 'past-absolute-cap', 'payload' => '', 'last_activity' => $now->getTimestamp(), 'created_at' => $now->subDays($absolute + 1)],
        ['id' => 'live', 'payload' => '', 'last_activity' => $now->getTimestamp(), 'created_at' => $now->subDays(1)],
    ]);
});

it('deletes only expired rows from each table', function () {
    $this->artisan('platform:prune-operational-tables')->assertSuccessful();

    expect(CocApiRequest::query()->count())->toBe(1)
        ->and(DB::table('failed_jobs')->count())->toBe(1)
        ->and(DB::table('cache')->pluck('key')->all())->toBe(['live'])
        ->and(DB::table('cache_locks')->pluck('key')->all())->toBe(['live-lock'])
        ->and(DB::table('sessions')->pluck('id')->all())->toBe(['live']);
});

it('counts without deleting on a dry run', function () {
    $this->artisan('platform:prune-operational-tables', ['--dry-run' => true])
        ->expectsOutputToContain('would delete 1')
        ->assertSuccessful();

    expect(CocApiRequest::query()->count())->toBe(2)
        ->and(DB::table('sessions')->count())->toBe(3);
});

it('is scheduled daily at 02:00, once, without overlap', function () {
    $event = collect(app(Schedule::class)->events())->first(fn ($e) => str_contains((string) $e->command, 'platform:prune-operational-tables'));

    expect($event->expression)->toBe('0 2 * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});
