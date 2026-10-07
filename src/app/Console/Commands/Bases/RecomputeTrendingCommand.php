<?php

namespace App\Console\Commands\Bases;

use App\Domain\Bases\Services\TrendingService;
use Illuminate\Console\Command;

/**
 * Trending scores (specs/20 §2, §3): every 15 minutes for recent bases, nightly with `--all`.
 */
class RecomputeTrendingCommand extends Command
{
    protected $signature = 'bases:recompute-trending {--all : Score every published base, not only recent ones}';

    protected $description = 'Recompute base trending scores';

    public function handle(TrendingService $trending): int
    {
        $count = $trending->recompute((bool) $this->option('all'));
        $this->info("Scored {$count} bases.");

        return self::SUCCESS;
    }
}
