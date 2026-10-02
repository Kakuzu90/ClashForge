<?php

namespace App\Domain\CocIntegration\Support;

use App\Domain\CocIntegration\Data\CocTag;
use App\Domain\CocIntegration\Models\CocApiRequest;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Writes `coc_api_requests` (specs/09 §4): one row per outbound call and per cache hit. The log is
 * forensics, not the source of truth, so a failed insert is logged and never fails the call.
 */
final class CocRequestLog
{
    public function record(string $endpoint, ?CocTag $tag, ?int $status, int $durationMs, bool $cached = false, ?string $errorCode = null): void
    {
        try {
            CocApiRequest::query()->create([
                'endpoint' => $endpoint,
                'tag' => $tag?->value,
                'status_code' => $status,
                'duration_ms' => max(0, $durationMs),
                'was_cached' => $cached,
                'error_code' => $errorCode === null ? null : substr($errorCode, 0, 64),
            ]);
        } catch (Throwable $e) {
            Log::warning('coc.request_log_failed', ['endpoint' => $endpoint, 'error' => $e::class]);
        }
    }
}
