<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// specs/20 §3. Nothing starts at :00; every task is overlap-safe and runs on one server.

// Liveness marker for /health; deliberately every minute, including :00. No summary log line: it
// would be 1,440 identical lines a day. Short mutexes so a killed run cannot stall it for a day.
Schedule::command('platform:heartbeat')
    ->everyMinute()
    ->withoutOverlapping(5)
    ->onOneServer()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'platform:heartbeat']));

Schedule::command('platform:check-health')
    ->everyFiveMinutes()
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'platform:check-health']));

Schedule::command('assets:verify-pack')
    ->weeklyOn(0, '05:15')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'assets:verify-pack']));

// Every 15 minutes (specs/20 §3), offset to :07 so it never starts at :00.
Schedule::command('moderation:expire-sanctions')
    ->cron('7-59/15 * * * *')
    ->withoutOverlapping(30)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'moderation:expire-sanctions']));

// Every 5 minutes, offset to :02 (specs/09 §6): due account syncs, within the background budget.
// Short mutex: a missed tick is picked up by the next one.
Schedule::command('coc:sync-accounts')
    ->cron('2-59/5 * * * *')
    ->withoutOverlapping(10)
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'coc:sync-accounts']));

// Hourly, offset to :25 (specs/13 §5): unanswered disputes go to the admins, abandoned ones close.
Schedule::command('coc:process-disputes')
    ->hourlyAt(25)
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'coc:process-disputes']));

Schedule::command('media:sweep-orphans')
    ->hourlyAt(20)
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'media:sweep-orphans']));

Schedule::command('media:retry-failed')
    ->cron('45 */6 * * *')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'media:retry-failed']));

// Monthly, on the 3rd (specs/11 "Spam and fake accounts"). A failed download keeps the current list.
Schedule::command('auth:refresh-disposable-domains')
    ->monthlyOn(3, '04:20')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'auth:refresh-disposable-domains']));

Schedule::command('platform:prune-operational-tables')
    ->dailyAt('02:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'platform:prune-operational-tables']));

Schedule::command('notifications:prune')
    ->dailyAt('02:15')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'notifications:prune']));

Schedule::command('media:purge-deleted')
    ->dailyAt('02:30')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'media:purge-deleted']));

// Daily at 02:45 (specs/20 §3): old CoC account snapshots thinned to one per day, then per week.
Schedule::command('coc:compact-snapshots')
    ->dailyAt('02:45')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'coc:compact-snapshots']));

Schedule::command('platform:anonymize-deleted')
    ->dailyAt('04:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'platform:anonymize-deleted']));

Schedule::command('auth:process-unverified')
    ->dailyAt((string) config('platform.auth.unverified_schedule_time'))
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'auth:process-unverified']));

// Daily at 04:15 (specs/20 §3), after the 04:00 anonymisation: banned owners' tags after 30 days.
Schedule::command('coc:release-banned-tags')
    ->dailyAt('04:15')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'coc:release-banned-tags']));

Schedule::command('media:reconcile-storage')
    ->weeklyOn(0, '05:00')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'media:reconcile-storage']));
