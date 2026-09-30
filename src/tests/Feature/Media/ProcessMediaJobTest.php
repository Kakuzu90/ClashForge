<?php

use App\Domain\Media\Enums\MediaCollection;
use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Events\MediaFailed;
use App\Domain\Media\Events\MediaReady;
use App\Domain\Media\Jobs\ProcessMediaJob;
use App\Domain\Media\Jobs\PurgeQuarantineObjectJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Services\MediaProcessingService;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Media\InteractsWithMedia;
use Tests\Support\Media\MediaFiles;

uses(InteractsWithMedia::class);

beforeEach(function () {
    $this->fakeMediaStorage();
    Event::fake([MediaReady::class, MediaFailed::class]);
});

function variantSizes(Media $media): array
{
    return $media->variants()->get()
        ->mapWithKeys(fn ($variant) => [$variant->variant->value => [$variant->width, $variant->height]])
        ->all();
}

it('turns an uploaded JPEG into WebP variants and marks it ready', function () {
    $bytes = MediaFiles::jpeg(1200, 900);
    $media = $this->uploadedMedia($bytes);

    ProcessMediaJob::dispatchSync($media->id);

    $media->refresh();
    $disk = $this->mediaDisk();

    expect($media->status)->toBe(MediaStatus::Ready)
        ->and($media->mime_type)->toBe('image/jpeg')
        ->and($media->extension)->toBe('jpg')
        ->and([$media->width, $media->height])->toBe([1200, 900])
        ->and($media->checksum_sha256)->toBe(hash('sha256', $bytes))
        ->and($media->processed_at)->not->toBeNull()
        // Never upscaled: the 1600 "full" variant keeps the 1200 original width.
        ->and(variantSizes($media))->toEqual(['full' => [1200, 900], 'card' => [800, 600], 'thumb' => [320, 240]]);

    foreach ($media->variants as $variant) {
        expect($variant->path)->toBe("public/base_screenshot/{$media->ulid}/{$variant->variant->value}.webp")
            ->and($variant->mime_type)->toBe('image/webp')
            ->and($disk->exists($variant->path))->toBeTrue()
            ->and(finfo_buffer(finfo_open(FILEINFO_MIME_TYPE), (string) $disk->get($variant->path)))->toBe('image/webp')
            ->and($variant->size_bytes)->toBe($disk->size($variant->path));
    }

    $disk->assertMissing($media->path);
    Event::assertDispatched(MediaReady::class, fn (MediaReady $e) => $e->mediaUlid === $media->ulid && $e->collection === MediaCollection::BaseScreenshot);
    Event::assertNotDispatched(MediaFailed::class);
});

it('accepts PNG and WebP originals', function (string $bytes, string $mime) {
    $media = $this->uploadedMedia($bytes);

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Ready)
        ->and($media->mime_type)->toBe($mime);
})->with([
    'png' => fn () => [MediaFiles::png(), 'image/png'],
    'webp' => fn () => [MediaFiles::webp(), 'image/webp'],
]);

it('centre-crops avatars to squares without upscaling', function () {
    $media = $this->uploadedMedia(MediaFiles::jpeg(600, 400), MediaCollection::Avatar);

    ProcessMediaJob::dispatchSync($media->id);

    expect(variantSizes($media->refresh()))->toEqual(['full' => [400, 400], 'card' => [128, 128], 'thumb' => [48, 48]]);
});

it('makes the variants listed in config for each collection', function () {
    foreach (MediaCollection::uploadable() as $collection) {
        $media = $this->uploadedMedia(MediaFiles::jpeg(), $collection);

        ProcessMediaJob::dispatchSync($media->id);

        expect(array_keys(variantSizes($media->refresh())))->toEqualCanonicalizing(array_keys($collection->variants()));
    }
});

it('does nothing for rows that are not waiting for processing', function (string $state) {
    $media = Media::factory()->{$state}()->create();
    $before = $media->status;

    app(MediaProcessingService::class)->process($media->id);

    expect($media->refresh()->status)->toBe($before)
        ->and($media->variants()->count())->toBe(0);
    Event::assertNothingDispatched();
})->with(['ready', 'failed', 'quarantined']);

it('can run twice without duplicating variants', function () {
    $media = $this->uploadedMedia(MediaFiles::jpeg());
    $original = $media->path;

    ProcessMediaJob::dispatchSync($media->id);
    $firstPaths = $media->variants()->pluck('path')->sort()->values()->all();

    // A worker killed after writing variants but before the row update leaves it in `processing`.
    $media->refresh()->forceFill(['status' => MediaStatus::Processing])->save();
    $this->mediaDisk()->put($original, MediaFiles::jpeg());
    $media->forceFill(['size_bytes' => $this->mediaDisk()->size($original)])->save();

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Ready)
        ->and($media->variants()->pluck('path')->sort()->values()->all())->toBe($firstPaths);
});

it('fails when the object never reached storage', function () {
    $media = Media::factory()->uploaded()->create();

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Failed)
        ->and($media->failure_reason)->toBe(MediaFailureReason::ObjectMissing);
    Event::assertDispatched(MediaFailed::class, fn (MediaFailed $e) => $e->status === MediaStatus::Failed && $e->reason === MediaFailureReason::ObjectMissing);
});

it('fails images under the minimum dimensions and drops the original', function () {
    $media = $this->uploadedMedia(MediaFiles::jpeg((int) config('media.image.min_width') - 1, 400));

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Failed)
        ->and($media->failure_reason)->toBe(MediaFailureReason::TooSmall);
    $this->mediaDisk()->assertMissing($media->path);
});

it('fails an image whose pixels cannot be decoded', function () {
    $media = $this->uploadedMedia(MediaFiles::pngHeaderOnly(400, 400));

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->failure_reason)->toBe(MediaFailureReason::Undecodable);
});

it('marks the upload failed but keeps the original when the job gives up', function () {
    $media = $this->uploadedMedia(MediaFiles::jpeg());
    $media->forceFill(['status' => MediaStatus::Processing])->save();

    (new ProcessMediaJob($media->id))->failed(new RuntimeException('timed out'));

    expect($media->refresh()->status)->toBe(MediaStatus::Failed)
        ->and($media->failure_reason)->toBe(MediaFailureReason::ProcessingError);
    $this->mediaDisk()->assertExists($media->path);
    Event::assertDispatched(MediaFailed::class);
});

it('goes back on the queue when the temp volume is too full', function () {
    config(['media.processing.min_free_bytes' => PHP_INT_MAX]);
    $media = $this->uploadedMedia(MediaFiles::jpeg());

    $job = (new ProcessMediaJob($media->id))->withFakeQueueInteractions();
    $job->handle(app(MediaProcessingService::class));

    $job->assertReleased(delay: (int) config('media.processing.release_delay'));
    expect($media->refresh()->status)->toBe(MediaStatus::Processing);
});

it('removes its temp directory', function () {
    $media = $this->uploadedMedia(MediaFiles::jpeg());

    ProcessMediaJob::dispatchSync($media->id);

    expect(app(MediaProcessingService::class)->tempDir($media->ulid))->not->toBeDirectory();
});

it('takes its queue, retry budget, timeout and backoff from config', function () {
    Date::setTestNow('2026-09-30 12:00:00');
    $job = new ProcessMediaJob(1);

    expect($job->queue)->toBe(config('media.processing.queue'))
        // Releases for a full temp volume wait inside the window; only exceptions and timeouts use up the budget.
        ->and($job->maxExceptions)->toBe(config('media.processing.max_exceptions'))
        ->and($job->retryUntil()->getTimestamp())->toBe(Date::now()->addMinutes((int) config('media.processing.retry_window_minutes'))->getTimestamp())
        ->and($job->failOnTimeout)->toBeFalse()
        ->and($job->timeout)->toBe(config('media.processing.timeout'))
        ->and($job->backoff())->toBe(config('media.processing.backoff'))
        ->and($job->uniqueId())->toBe('1')
        // A reservation must outlive the job, or a long run is picked up twice (specs/20 §5).
        ->and(config('queue.connections.media.retry_after'))->toBeGreaterThan($job->timeout);
});

it('accepts the same file twice as separate uploads with the same checksum', function () {
    $bytes = MediaFiles::jpeg();
    $first = $this->uploadedMedia($bytes);
    $second = $this->uploadedMedia($bytes);

    ProcessMediaJob::dispatchSync($first->id);
    ProcessMediaJob::dispatchSync($second->id);

    expect($first->refresh()->status)->toBe(MediaStatus::Ready)
        ->and($second->refresh()->status)->toBe(MediaStatus::Ready)
        ->and($first->checksum_sha256)->toBe($second->checksum_sha256)
        ->and($first->variants()->pluck('path')->intersect($second->variants()->pluck('path')))->toBeEmpty();
});

it('writes private variants with a no-store cache header', function () {
    config(['media.collections.evidence.variants' => ['card' => ['width' => 800]]]);
    $disk = Mockery::mock($this->mediaDisk())->makePartial();
    Storage::set((string) config('media.disk'), $disk);
    $media = $this->uploadedMedia(MediaFiles::jpeg(), MediaCollection::Evidence);

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Ready);
    $disk->shouldHaveReceived('put')->withArgs(fn (string $path, $contents, array $options = []) => str_starts_with($path, 'private/evidence/')
        && ($options['CacheControl'] ?? null) === config('media.private_cache_control'));
});

it('deletes the quarantine key again once its upload URL has expired', function (string $outcome) {
    Queue::fake([PurgeQuarantineObjectJob::class]);
    Date::setTestNow('2026-09-30 12:00:00');
    $bytes = $outcome === 'ready' ? MediaFiles::jpeg() : MediaFiles::jpeg(100, 100);
    $media = $this->uploadedMedia($bytes);

    ProcessMediaJob::dispatchSync($media->id);

    $expected = $media->created_at->addSeconds((int) config('media.intent_ttl') + (int) config('media.quarantine_recheck_margin'));
    Queue::assertPushedOn(config('media.cleanup_queue'), PurgeQuarantineObjectJob::class, fn (PurgeQuarantineObjectJob $job) => $job->mediaId === $media->id
        && $job->delay->getTimestamp() === $expected->getTimestamp());

    // A client re-uploads to the still-valid URL after processing; the delayed purge removes it.
    $this->mediaDisk()->put($media->path, str_repeat('x', 1024));
    (new PurgeQuarantineObjectJob($media->id))->handle();

    $this->mediaDisk()->assertMissing($media->path);
})->with(['ready', 'failed']);

it('keeps quarantined and in-flight originals when the purge runs', function (string $state) {
    $media = $this->uploadedMedia(MediaFiles::jpeg());
    $media->forceFill(['status' => MediaStatus::from($state)])->save();

    (new PurgeQuarantineObjectJob($media->id))->handle();

    $this->mediaDisk()->assertExists($media->path);
})->with(['quarantined', 'uploaded', 'processing']);
