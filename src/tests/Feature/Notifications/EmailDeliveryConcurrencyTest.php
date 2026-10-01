<?php

use App\Domain\Notifications\Models\EmailDelivery;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

it('shares the last daily slot between concurrent postgres workers', function () {
    $user = User::factory()->create();
    $database = config('database.connections.pgsql');
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => $database['host'],
        'DB_PORT' => (string) $database['port'], 'DB_DATABASE' => $database['database'],
        'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password'], 'DB_URL' => '',
        'CACHE_STORE' => 'database', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    ];
    $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Date::setTestNow('2026-10-01 12:00:00');
config(['platform.notifications.email_per_day' => 1]);
Illuminate\Support\Facades\Event::listen(Illuminate\Mail\Events\MessageSending::class, fn () => usleep(250000));
app(App\Domain\Notifications\Services\EmailDeliveryService::class)->send((int) $argv[1], App\Domain\Notifications\Enums\NotificationType::MediaProcessingFailed, $argv[2], ['collection' => 'avatar']);
PHP;
    // Child connections need a committed fixture; restore the test transaction after cleanup.
    DB::commit();
    $workers = [];
    try {
        foreach (['concurrent-a', 'concurrent-b'] as $event) {
            $worker = new Process([PHP_BINARY, '-r', $code, (string) $user->id, $event], base_path(), $environment);
            $worker->setTimeout(15);
            $worker->start();
            $workers[] = $worker;
        }
        foreach ($workers as $worker) {
            $worker->wait();
            expect($worker->isSuccessful())->toBeTrue();
        }
        expect(EmailDelivery::query()->where('user_id', $user->id)->count())->toBe(1);
    } finally {
        foreach ($workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        Cache::store('database')->forget('notifications:email:'.$user->id.':2026-10-01');
        $user->forceDelete();
        DB::beginTransaction();
    }
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'Real row-lock concurrency is verified on PostgreSQL.');
