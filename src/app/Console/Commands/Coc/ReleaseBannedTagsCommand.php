<?php

namespace App\Console\Commands\Coc;

use App\Domain\PlayerAccounts\Services\BannedTagRelease;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Releases the tags of owners banned for `coc.accounts.ban_release_days` (specs/13 §6). Daily at
 * 04:15; safe to run twice.
 */
class ReleaseBannedTagsCommand extends Command
{
    protected $signature = 'coc:release-banned-tags';

    protected $description = 'Release the CoC tags of owners whose ban has run its waiting period';

    public function handle(BannedTagRelease $release): int
    {
        $count = $release->run();

        $this->components->info("Released {$count} accounts.");
        Log::info('coc.release_banned_tags', ['released' => $count]);

        return self::SUCCESS;
    }
}
