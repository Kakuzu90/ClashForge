<?php

namespace App\Console\Commands\Notifications;

use App\Domain\Notifications\Services\NotificationPruner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Nightly notification retention (specs/16 §7, specs/20 §3 at 02:15). Runs inline: it is a few
 * chunked deletes. Safe to run twice.
 */
class PruneNotificationsCommand extends Command
{
    protected $signature = 'notifications:prune {--dry-run : Count what would be deleted without deleting it}';

    protected $description = 'Delete old notifications and trim each account to its cap';

    public function handle(NotificationPruner $pruner): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = $pruner->prune($dryRun);

        $this->components->info(($dryRun ? 'Would delete' : 'Deleted')." {$result['read']} read, {$result['unread']} unread and {$result['over_cap']} over-cap notifications.");
        Log::info('notifications.prune', [...$result, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }
}
