<?php

namespace App\Console\Commands\Auth;

use App\Domain\Auth\Services\DisposableEmailDomains;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Monthly refresh of the disposable-email blocklist (specs/11 "Spam and fake accounts"). Writes the
 * downloaded list to storage only when it parses and is not suspiciously short, so a bad download
 * never empties the blocklist; the committed file stays the fallback.
 */
class RefreshDisposableDomainsCommand extends Command
{
    protected $signature = 'auth:refresh-disposable-domains';

    protected $description = 'Download the latest disposable-email domain list';

    public function handle(): int
    {
        try {
            $response = Http::timeout((int) config('platform.auth.disposable_domains_timeout'))->get((string) config('platform.auth.disposable_domains_url'));
        } catch (Throwable $e) {
            return $this->failed('unreachable', $e->getMessage());
        }

        if (! $response->successful()) {
            return $this->failed('http_'.$response->status());
        }

        $count = count(DisposableEmailDomains::parse($response->body()));

        if ($count < (int) config('platform.auth.disposable_domains_min')) {
            return $this->failed('too_short', "{$count} domains");
        }

        $path = (string) config('platform.auth.disposable_domains_refreshed');
        File::ensureDirectoryExists(dirname($path));
        // Written beside the live file, then renamed over it, so no reader sees half a list.
        $temporary = $path.'.'.getmypid().'.tmp';
        File::put($temporary, $response->body());
        File::move($temporary, $path);

        $this->components->info("Saved {$count} disposable domains.");
        Log::info('auth.disposable_domains_refreshed', ['domains' => $count]);

        return self::SUCCESS;
    }

    private function failed(string $reason, string $detail = ''): int
    {
        $this->components->error("Kept the current list ({$reason}).");
        Log::error('auth.disposable_domains_refresh_failed', ['reason' => $reason, 'detail' => $detail]);

        return self::FAILURE;
    }
}
