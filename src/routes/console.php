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
