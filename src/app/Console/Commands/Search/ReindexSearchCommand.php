<?php

namespace App\Console\Commands\Search;

use App\Domain\Search\Contracts\SearchService;
use App\Domain\Search\Enums\SearchType;
use Illuminate\Console\Command;

/**
 * Rebuilds search data (specs/19 §7). Triggers keep the vectors current, so this is for repairs
 * and after a change to how a vector is built.
 */
class ReindexSearchCommand extends Command
{
    protected $signature = 'search:reindex {type? : bases, players or accounts; all when left out}';

    protected $description = 'Rebuild search vectors';

    public function handle(SearchService $search): int
    {
        $argument = $this->argument('type');
        $type = is_string($argument) ? SearchType::tryFrom($argument) : null;

        if (is_string($argument) && ($type === null || $type === SearchType::All)) {
            $this->error('Pick bases, players or accounts.');

            return self::INVALID;
        }

        $this->info('Reindexed '.$search->reindex($type).' rows.');

        return self::SUCCESS;
    }
}
