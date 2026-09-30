<?php

namespace App\Domain\Media\Jobs;

use App\Domain\Media\Exceptions\InsufficientTempSpace;
use App\Domain\Media\Services\MediaProcessingService;
use DateTimeInterface;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Validates and re-encodes one upload on the `media` queue (specs/20 §2). The payload is the id
 * only; limits come from config('media.processing').
 */
class ProcessMediaJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Exceptions and timeouts allowed before the upload is marked failed. Releases (full temp
     * volume) do not count; they only wait inside the retry window.
     */
    public int $maxExceptions;

    public int $timeout;

    public int $uniqueFor;

    // A timeout counts toward maxExceptions, so it is retried once like any other failure.
    public bool $failOnTimeout = false;

    public function __construct(public readonly int $mediaId)
    {
        $this->onQueue((string) config('media.processing.queue'));
        $this->maxExceptions = (int) config('media.processing.max_exceptions');
        $this->timeout = (int) config('media.processing.timeout');
        // Held until the job finishes; must outlive a reservation on the media connection.
        $this->uniqueFor = (int) config('queue.connections.media.retry_after');
    }

    public function uniqueId(): string
    {
        return (string) $this->mediaId;
    }

    public function retryUntil(): DateTimeInterface
    {
        return Date::now()->addMinutes((int) config('media.processing.retry_window_minutes'));
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        /** @var list<int> */
        return config('media.processing.backoff');
    }

    public function handle(MediaProcessingService $service): void
    {
        $started = microtime(true);
        Log::info('media.process.start', ['media_id' => $this->mediaId]);

        try {
            $service->process($this->mediaId);
        } catch (InsufficientTempSpace $e) {
            Log::error('media.process.disk_full', ['media_id' => $this->mediaId, 'detail' => $e->getMessage()]);
            $this->release((int) config('media.processing.release_delay'));

            return;
        }

        Log::info('media.process.end', [
            'media_id' => $this->mediaId,
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        app(MediaProcessingService::class)->giveUp($this->mediaId);
    }
}
