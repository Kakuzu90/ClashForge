<?php

use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

it('serializes verification and settings writes with purge on postgres', function (string $first, string $secondAction) {
    Date::setTestNow('2026-10-01 12:00:00');
    $account = User::factory()->warnedUnverified()->create();
    $database = config('database.connections.pgsql');
    $environment = [
        'APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_HOST' => $database['host'],
        'DB_PORT' => (string) $database['port'], 'DB_DATABASE' => $database['database'],
        'DB_USERNAME' => $database['username'], 'DB_PASSWORD' => $database['password'], 'DB_URL' => '',
        'CACHE_STORE' => 'array', 'MAIL_MAILER' => 'array', 'QUEUE_CONNECTION' => 'sync',
    ];
    $code = <<<'PHP'
require 'vendor/autoload.php';
$app = require 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
Illuminate\Support\Facades\Date::setTestNow('2026-10-01 12:00:00');
Illuminate\Support\Facades\Queue::fake();
// Audit assertions live in the feature suite; committed race fixtures must not leave immutable rows.
$app->instance(App\Domain\Audit\Services\AuditLogger::class, Mockery::mock(App\Domain\Audit\Services\AuditLogger::class)->shouldIgnoreMissing());
Illuminate\Support\Facades\DB::transaction(function () use ($argv): void {
    $account = App\Models\User::withTrashed()->findOrFail((int) $argv[1]);
    $ulid = $account->ulid;
    $hash = sha1(strtolower($account->email));
    if ($argv[3] === 'first') {
        App\Models\User::query()->whereKey($account->id)->lockForUpdate()->firstOrFail();
        echo "locked\n";
        flush();
        $until = microtime(true) + 10;
        while (! file_exists($argv[4])) {
            if (microtime(true) > $until) { throw new RuntimeException('Barrier timeout'); }
            usleep(10000);
        }
    } else {
        echo "ready\n";
        flush();
    }
    try {
        $result = match ($argv[2]) {
            'verify' => app(App\Domain\Auth\Services\EmailVerificationService::class)->confirm($ulid, $hash, null, null, null)->value,
            'purge' => (int) app(App\Domain\Auth\Services\AccountDeletionService::class)->purgeUnverified($account->id),
            'profile' => app(App\Domain\Users\Services\ProfileService::class)->update($account, App\Domain\Users\Data\UpdateProfileData::fromValidated('Private name', 'Private bio', null, [], null, [])),
            'email' => app(App\Domain\Auth\Services\EmailChangeService::class)->request($account, 'new@example.com', 'password', null),
            'role' => (int) app(App\Domain\Auth\Services\RoleAssignmentService::class)->assign($account, App\Domain\Auth\Enums\Role::Admin, App\Domain\Audit\Data\AuditActorData::console()),
        };
    } catch (Illuminate\Database\Eloquent\ModelNotFoundException) {
        $result = 'rejected';
    }
    echo 'result:'.$result."\n";
});
PHP;
    $barrier = tempnam(sys_get_temp_dir(), 'unverified-barrier-');
    unlink($barrier);
    $workers = [];
    // Separate worker connections need a committed fixture; restore the test transaction below.
    DB::commit();
    try {
        $worker = new Process([PHP_BINARY, '-r', $code, (string) $account->id, $first, 'first', $barrier], base_path(), $environment);
        $worker->setTimeout(15);
        $workers[] = $worker;
        $worker->start();
        expect($worker->waitUntil(fn ($type, $output) => str_contains($output, 'locked')))->toBeTrue();
        $second = new Process([PHP_BINARY, '-r', $code, (string) $account->id, $secondAction, 'second', $barrier], base_path(), $environment);
        $second->setTimeout(15);
        $workers[] = $second;
        $second->start();
        expect($second->waitUntil(fn ($type, $output) => str_contains($output, 'ready')))->toBeTrue();
        touch($barrier);
        foreach ($workers as $process) {
            $process->wait();
            expect($process->isSuccessful())->toBeTrue();
        }
        $fresh = User::withTrashed()->findOrFail($account->id);
        expect($fresh->deleted_at !== null)->toBe(! in_array($first, ['verify', 'role'], true))
            ->and($fresh->email_verified_at !== null)->toBe($first === 'verify');
        if ($secondAction === 'verify') {
            expect($second->getOutput())->toContain('result:invalid');
        } elseif ($first === 'purge') {
            expect($second->getOutput())->toContain('result:rejected');
        } elseif (in_array($first, ['verify', 'role'], true)) {
            expect($second->getOutput())->toContain('result:0');
        }
        if ($first !== 'verify') {
            expect($fresh->profile?->bio)->toBeNull()->and($fresh->pending_email)->toBeNull();
        }
    } finally {
        foreach ($workers as $process) {
            if ($process->isRunning()) {
                $process->stop();
            }
        }
        if (file_exists($barrier)) {
            unlink($barrier);
        }
        User::withTrashed()->findOrFail($account->id)->forceDelete();
        DB::beginTransaction();
    }
})->with([['verify', 'purge'], ['purge', 'verify'], ['profile', 'purge'], ['purge', 'profile'], ['email', 'purge'], ['purge', 'email'], ['role', 'purge'], ['purge', 'role']])->skip(fn () => DB::getDriverName() !== 'pgsql', 'Real account-lock races are verified on PostgreSQL.');
