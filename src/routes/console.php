<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// specs/20 §3. Nothing starts at :00; every task is overlap-safe and runs on one server.

Schedule::command('assets:verify-pack')
    ->weeklyOn(0, '05:15')
    ->withoutOverlapping()
    ->onOneServer()
    ->runInBackground()
    ->onFailure(fn () => Log::error('schedule.failed', ['command' => 'assets:verify-pack']));
