<?php

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Services\MediaLifecycleService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Deletes claimed media from storage and the database (specs/20 §2). Idempotent: rows already
 * gone are skipped and missing objects are not an error.
 */
class DeleteMediaObjectsJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // Below the `database` connection's retry_after (90 s), or a slow batch is reserved twice.
    public int $timeout = 75;

    /**
     * @param  list<int>  $mediaIds  at most media.lifecycle.batch_size
     */
    public function __construct(public readonly array $mediaIds)
    {
        $this->onQueue((string) config('media.cleanup_queue'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function handle(MediaLifecycleService $lifecycle): void
    {
        $started = microtime(true);
        $deleted = $lifecycle->deleteMedia($this->mediaIds);

        Log::info('media.objects_deleted', [
            'first_id' => $this->mediaIds[0] ?? null,
            'last_id' => $this->mediaIds[count($this->mediaIds) - 1] ?? null,
            'requested' => count($this->mediaIds),
            'deleted' => $deleted,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }
}
