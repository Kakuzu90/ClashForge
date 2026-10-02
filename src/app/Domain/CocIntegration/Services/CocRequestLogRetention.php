<?php

namespace App\Domain\CocIntegration\Services;

use App\Domain\CocIntegration\Models\CocApiRequest;
use Illuminate\Support\Facades\Date;

/**
 * Drops `coc_api_requests` rows past `coc.request_log.retention_days` (specs/07: a 7-day rolling
 * log). Run by platform:prune-operational-tables.
 */
class CocRequestLogRetention
{
    public function prune(bool $dryRun = false): int
    {
        $query = CocApiRequest::query()->where('created_at', '<', Date::now()->subDays((int) config('coc.request_log.retention_days')));

        return $dryRun ? $query->count() : $query->delete();
    }
}
