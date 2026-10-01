<?php

namespace App\Domain\Auth\Services;

use App\Domain\Auth\Enums\Role;
use App\Domain\Auth\Enums\UserStatus;
use App\Domain\Auth\Jobs\SendVerificationLifecycleEmailJob;
use App\Domain\Auth\Notifications\VerificationLifecycleEmail;
use App\Domain\Auth\Notifications\VerifyEmailNotification;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class UnverifiedAccountLifecycleService
{
    public function __construct(private readonly AccountDeletionService $deletion) {}

    /** @return array{reminders: int, warnings: int, purged: int} */
    public function process(bool $dryRun = false): array
    {
        $queueConnection = config('queue.connections.database.connection');
        if ($queueConnection !== null && $queueConnection !== DB::getDefaultConnection()) {
            throw new \LogicException('Lifecycle dispatch requires the queue and accounts to share a database connection.');
        }
        $counts = ['reminders' => 0, 'warnings' => 0, 'purged' => 0];
        User::query()->whereNull('email_verified_at')->where('role', Role::User)
            ->where('status', '!=', UserStatus::PendingDeletion)
            ->where('created_at', '<=', Date::now()->subDays((int) config('platform.auth.unverified_reminder_days')))
            ->select('id')->chunkById((int) config('platform.auth.unverified_batch_size'), function ($accounts) use (&$counts, $dryRun): void {
                foreach ($accounts as $account) {
                    $action = $this->processAccount($account->id, $dryRun);
                    if ($action !== null) {
                        $counts[$action]++;
                    }
                }
            });

        return $counts;
    }

    /** @return 'reminders'|'warnings'|'purged'|null */
    private function processAccount(int $userId, bool $dryRun): ?string
    {
        return DB::transaction(function () use ($userId, $dryRun) {
            $account = User::query()->whereKey($userId)->lockForUpdate()->first();
            if ($account === null || ! Gate::forUser(null)->allows('processUnverifiedLifecycle', $account)) {
                return null;
            }

            if (Gate::forUser(null)->allows('expireUnverified', $account)) {
                if (! $dryRun) {
                    $this->deletion->purgeUnverified($account->id);
                }

                return 'purged';
            }

            $warning = $account->created_at->lessThanOrEqualTo(Date::now()->subDays((int) config('platform.auth.unverified_warning_days')));
            $column = $warning ? 'verification_warning_queued_at' : 'verification_reminder_queued_at';
            if ($account->getAttribute($column) !== null) {
                return null;
            }

            if (! $dryRun) {
                $key = Str::lower((string) Str::ulid());
                $account->forceFill([$column => Date::now(), 'verification_notice_key' => $key])->save();
                // The database queue insert and marker commit together, so a crash cannot strand a marked notice.
                Bus::dispatch((new SendVerificationLifecycleEmailJob($account->id, self::emailBinding($account), $warning, $key))->beforeCommit());
            }

            return $warning ? 'warnings' : 'reminders';
        });
    }

    public static function emailBinding(User $account): string
    {
        return hash_hmac('sha256', strtolower($account->email), (string) config('app.key'));
    }

    public function send(int $userId, string $emailBinding, bool $warning, string $dispatchKey): void
    {
        DB::transaction(function () use ($userId, $emailBinding, $warning, $dispatchKey): void {
            $account = User::query()->whereKey($userId)->lockForUpdate()->first();
            if ($account === null || $account->verification_notice_key !== $dispatchKey) {
                return;
            }

            $queued = $warning ? $account->verification_warning_queued_at : $account->verification_reminder_queued_at;
            $sentColumn = $warning ? 'verification_warning_sent_at' : 'verification_reminder_sent_at';
            if (! Gate::forUser(null)->allows('processUnverifiedLifecycle', $account)
                || ! hash_equals(self::emailBinding($account), $emailBinding)) {
                if ($account->getAttribute($sentColumn) === null) {
                    Gate::forUser(null)->authorize('discardUnverifiedNotice', $account);
                    $queuedColumn = $warning ? 'verification_warning_queued_at' : 'verification_reminder_queued_at';
                    $account->forceFill([$queuedColumn => null, 'verification_notice_key' => null])->save();
                }

                return;
            }
            if ($queued === null || $account->getAttribute($sentColumn) !== null
                || (! $warning && $account->verification_warning_queued_at !== null)) {
                return;
            }

            $deadline = $account->created_at->addDays((int) config('platform.auth.unverified_purge_days'));
            if ($warning) {
                $deadline = $deadline->max(Date::now()->addDays((int) config('platform.auth.unverified_warning_grace_days')));
            }
            Mail::to($account->email)->send(new VerificationLifecycleEmail(
                $warning, VerifyEmailNotification::url($account), $deadline->utc()->format('j F Y, H:i').' UTC',
            ));
            $account->forceFill([$sentColumn => Date::now()])->save();
        });
    }
}
