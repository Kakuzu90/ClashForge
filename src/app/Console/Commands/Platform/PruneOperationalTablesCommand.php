<?php

namespace App\Console\Commands\Platform;

use App\Domain\CocIntegration\Services\CocRequestLogRetention;
use App\Support\Maintenance\OperationalTablePruner;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class PruneOperationalTablesCommand extends Command
{
    protected $signature = 'platform:prune-operational-tables {--dry-run : Count what would be deleted without deleting it}';

    protected $description = 'Delete expired operational rows: CoC request log, failed jobs, cache, sessions (specs/20 §2)';

    public function handle(OperationalTablePruner $pruner, CocRequestLogRetention $cocLog): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $result = ['coc_api_requests' => $cocLog->prune($dryRun), ...$pruner->prune($dryRun)];

        foreach ($result as $table => $count) {
            $this->components->twoColumnDetail($table, ($dryRun ? 'would delete ' : 'deleted ').$count);
        }

        Log::info('platform.prune_operational_tables', [...$result, 'dry_run' => $dryRun]);

        return self::SUCCESS;
    }
}
