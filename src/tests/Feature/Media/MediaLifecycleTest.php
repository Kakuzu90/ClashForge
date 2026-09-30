<?php

use App\Domain\Media\Enums\MediaFailureReason;
use App\Domain\Media\Enums\MediaStatus;
use App\Domain\Media\Enums\VariantName;
use App\Domain\Media\Events\MediaFailed;
use App\Domain\Media\Events\MediaRetriesExhausted;
use App\Domain\Media\Exceptions\ObjectsNotDeleted;
use App\Domain\Media\Jobs\DeleteMediaObjectsJob;
use App\Domain\Media\Jobs\ProcessMediaJob;
use App\Domain\Media\Models\Media;
use App\Domain\Media\Models\MediaVariant;
use App\Domain\Media\Services\MediaLifecycleService;
use App\Domain\Media\Services\MediaProcessingService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Media\InteractsWithMedia;
use Tests\Support\Media\MediaFiles;

uses(InteractsWithMedia::class);

beforeEach(function () {
    $this->fakeMediaStorage();
    Date::setTestNow('2026-09-30 12:00:00');
});

/**
 * A ready row with its variant objects on the faked disk.
 */
function readyMediaWithObjects(array $attributes = [], ?Closure $state = null): Media
{
    $factory = Media::factory()->ready();
    $media = ($state ? $state($factory) : $factory)->create($attributes);
    $disk = test()->mediaDisk();

    foreach ([VariantName::Card, VariantName::Thumb] as $variant) {
        $path = "public/base_screenshot/{$media->ulid}/{$variant->value}.webp";
        MediaVariant::factory()->for($media)->create(['variant' => $variant, 'path' => $path]);
        $disk->put($path, 'webp');
    }

    return $media;
}

describe('media:sweep-orphans', function () {
    it('claims expired unattached media in every sweepable state and deletes it', function (Closure $state) {
        $media = $state(Media::factory()->expired())->create();
        $this->mediaDisk()->put($media->path, 'bytes');

        $this->artisan('media:sweep-orphans')->assertSuccessful();

        expect(Media::withTrashed()->find($media->id))->toBeNull();
        $this->mediaDisk()->assertMissing($media->path);
    })->with([
        'pending, bytes PUT but never completed' => fn ($f) => $f,
        'uploaded' => fn ($f) => $f->uploaded(),
        'ready, never attached' => fn ($f) => $f->ready(),
        'failed' => fn ($f) => $f->failed(MediaFailureReason::ProcessingError),
    ]);

    it('deletes every variant of swept ready media', function () {
        $media = readyMediaWithObjects(state: fn ($f) => $f->expired());

        $this->artisan('media:sweep-orphans')->assertSuccessful();

        expect(MediaVariant::query()->where('media_id', $media->id)->exists())->toBeFalse();
        $this->mediaDisk()->assertMissing("public/base_screenshot/{$media->ulid}/card.webp");
        $this->mediaDisk()->assertMissing("public/base_screenshot/{$media->ulid}/thumb.webp");
    });

    it('leaves fresh, attached, processing and quarantined media alone', function (Closure $state) {
        $media = $state(Media::factory())->create();

        $this->artisan('media:sweep-orphans')->assertSuccessful();

        expect($media->refresh()->status)->not->toBe(MediaStatus::Deleting);
    })->with([
        'not yet expired' => fn ($f) => $f,
        'attached' => fn ($f) => $f->ready()->attached()->state(['expires_at' => Date::now()->subDay()]),
        'processing' => fn ($f) => $f->processing()->expired(),
        'quarantined' => fn ($f) => $f->quarantined()->expired(),
    ]);

    it('queues one deletion job per batch of claimed rows', function () {
        Queue::fake();
        config(['media.lifecycle.batch_size' => 2]);
        Media::factory()->expired()->count(5)->create();

        $this->artisan('media:sweep-orphans')->assertSuccessful();

        Queue::assertPushed(DeleteMediaObjectsJob::class, 3);
        Queue::assertPushed(DeleteMediaObjectsJob::class, fn ($job) => count($job->mediaIds) <= 2 && $job->queue === config('media.cleanup_queue'));
        expect(Media::query()->where('status', MediaStatus::Deleting)->count())->toBe(5);
    });

    it('re-queues deletions that stalled, and alerts', function () {
        Queue::fake();
        Log::spy();
        $stalled = Media::factory()->create(['status' => MediaStatus::Deleting]);
        $recent = Media::factory()->create(['status' => MediaStatus::Deleting]);
        $stalled->forceFill(['updated_at' => Date::now()->subMinutes((int) config('media.lifecycle.stale_deleting_minutes') + 1)])->saveQuietly();

        $this->artisan('media:sweep-orphans')->assertSuccessful();

        Queue::assertPushed(DeleteMediaObjectsJob::class, fn ($job) => $job->mediaIds === [$stalled->id]);
        Log::shouldHaveReceived('error')->with('media.deletion_stalled', ['count' => 1, 'dry_run' => false])->once();
        Queue::assertNotPushed(DeleteMediaObjectsJob::class, fn ($job) => in_array($recent->id, $job->mediaIds, true));
    });

    it('changes nothing on a dry run', function () {
        Queue::fake();
        $media = Media::factory()->expired()->create();

        $this->artisan('media:sweep-orphans', ['--dry-run' => true])
            ->expectsOutputToContain('Would sweep 1')
            ->assertSuccessful();

        expect($media->refresh()->status)->toBe(MediaStatus::Pending);
        Queue::assertNothingPushed();
    });
});

describe('media:purge-deleted', function () {
    it('hard-deletes media soft-deleted past the recovery window', function () {
        $media = readyMediaWithObjects(['attachable_type' => 'base', 'attachable_id' => 1, 'expires_at' => null]);
        $media->delete();
        $this->travel((int) config('media.lifecycle.purge_after_days'))->days();

        $this->artisan('media:purge-deleted')->assertSuccessful();

        expect(Media::withTrashed()->find($media->id))->toBeNull();
        $this->mediaDisk()->assertMissing("public/base_screenshot/{$media->ulid}/card.webp");
    });

    it('keeps media inside the window, and quarantined media always', function () {
        $recent = Media::factory()->ready()->create();
        $recent->delete();
        $held = Media::factory()->quarantined()->create();
        $held->delete();
        $days = (int) config('media.lifecycle.purge_after_days');

        $this->travel($days)->days();
        $this->travel(-1)->minutes();
        $this->artisan('media:purge-deleted')->assertSuccessful();

        expect($recent->refresh()->status)->toBe(MediaStatus::Ready);

        $this->travel(30)->days();
        $this->artisan('media:purge-deleted')->assertSuccessful();

        expect(Media::withTrashed()->find($recent->id))->toBeNull()
            ->and($held->refresh()->status)->toBe(MediaStatus::Quarantined);
    });
});

describe('DeleteMediaObjectsJob', function () {
    it('is idempotent and tolerates objects that are already gone', function () {
        $media = readyMediaWithObjects(['status' => MediaStatus::Deleting]);
        $this->mediaDisk()->delete("public/base_screenshot/{$media->ulid}/card.webp");

        DeleteMediaObjectsJob::dispatchSync([$media->id]);
        DeleteMediaObjectsJob::dispatchSync([$media->id]);

        expect(Media::withTrashed()->find($media->id))->toBeNull();
        $this->mediaDisk()->assertMissing("public/base_screenshot/{$media->ulid}/thumb.webp");
    });

    it('keeps deleting the rest of a batch when one row is refused, then fails the job', function () {
        $stuck = Media::factory()->create(['status' => MediaStatus::Deleting, 'disk' => 'unconfigured']);
        $ok = readyMediaWithObjects(['status' => MediaStatus::Deleting]);

        expect(fn () => app(MediaLifecycleService::class)->deleteMedia([$stuck->id, $ok->id]))
            ->toThrow(ObjectsNotDeleted::class);

        expect(Media::withTrashed()->find($ok->id))->toBeNull()
            ->and($stuck->refresh()->status)->toBe(MediaStatus::Deleting);
        $this->mediaDisk()->assertMissing("public/base_screenshot/{$ok->ulid}/card.webp");
    });

    it('spares a claimed row that processing moved on before the job ran', function () {
        Queue::fake();
        $media = readyMediaWithObjects(state: fn ($f) => $f->expired());
        $this->artisan('media:sweep-orphans')->assertSuccessful();
        // e.g. the upload finished and the parent form attached it in the meantime.
        $media->refresh()->forceFill(['status' => MediaStatus::Ready, 'attachable_type' => 'base', 'attachable_id' => 1, 'expires_at' => null])->save();

        app(MediaLifecycleService::class)->deleteMedia([$media->id]);

        expect($media->refresh()->exists)->toBeTrue();
        $this->mediaDisk()->assertExists("public/base_screenshot/{$media->ulid}/card.webp");
    });

    it('retries with the low-queue policy inside the connection reservation', function () {
        $job = new DeleteMediaObjectsJob([1]);

        expect($job->tries)->toBe(3)
            ->and($job->backoff())->toBe([60, 300, 900])
            ->and($job->timeout)->toBeLessThan((int) config('queue.connections.database.retry_after'));
    });

    it('never deletes a row that was not claimed', function () {
        $media = readyMediaWithObjects();

        DeleteMediaObjectsJob::dispatchSync([$media->id]);

        expect($media->refresh()->exists)->toBeTrue();
        $this->mediaDisk()->assertExists("public/base_screenshot/{$media->ulid}/card.webp");
    });
});

describe('media:retry-failed', function () {
    it('re-queues young processing errors with attempts left', function () {
        Queue::fake();
        $media = Media::factory()->failed(MediaFailureReason::ProcessingError)->create(['processing_attempts' => 1]);

        $this->artisan('media:retry-failed')->assertSuccessful();

        expect($media->refresh()->status)->toBe(MediaStatus::Uploaded)
            ->and($media->failure_reason)->toBeNull();
        Queue::assertPushed(ProcessMediaJob::class, fn ($job) => $job->mediaId === $media->id);
    });

    it('skips other failures, old failures and exhausted attempts', function (Closure $state) {
        Queue::fake();
        $media = $state(Media::factory())->create();

        $this->artisan('media:retry-failed')->assertSuccessful();

        expect($media->refresh()->status)->toBe(MediaStatus::Failed);
        Queue::assertNotPushed(ProcessMediaJob::class);
    })->with([
        'deterministic rejection' => fn ($f) => $f->failed(MediaFailureReason::Undecodable)->state(['processing_attempts' => 1]),
        'older than the window' => fn ($f) => $f->failed(MediaFailureReason::ProcessingError)
            ->state(['processing_attempts' => 1, 'created_at' => Date::now()->subHours((int) config('media.lifecycle.retry_window_hours'))->subMinute()]),
        'attempts exhausted' => fn ($f) => $f->failed(MediaFailureReason::ProcessingError)
            ->state(['processing_attempts' => (int) config('media.lifecycle.retry_max_attempts')]),
    ]);

    it('stops after the configured number of attempts and fires MediaRetriesExhausted once', function () {
        Event::fake([MediaFailed::class, MediaRetriesExhausted::class]);
        Queue::fake();
        $max = (int) config('media.lifecycle.retry_max_attempts');
        $media = $this->uploadedMedia(MediaFiles::jpeg(800, 600));
        $service = app(MediaProcessingService::class);

        // Each attempt: the worker picks the row up, then the job gives up with a processing error.
        $failAttempt = function () use ($media, $service) {
            $media->refresh();
            $media->forceFill(['status' => MediaStatus::Processing, 'processing_attempts' => $media->processing_attempts + 1])->save();
            $service->giveUp($media->id);
        };

        $failAttempt();

        for ($i = 1; $i < $max; $i++) {
            $this->artisan('media:retry-failed')->assertSuccessful();
            expect($media->refresh()->status)->toBe(MediaStatus::Uploaded);
            $failAttempt();
        }

        expect($media->refresh()->processing_attempts)->toBe($max)
            ->and($media->status)->toBe(MediaStatus::Failed);
        Event::assertDispatchedTimes(MediaRetriesExhausted::class, 1);

        $this->artisan('media:retry-failed')->assertSuccessful();
        expect($media->refresh()->status)->toBe(MediaStatus::Failed);
    });
});

it('counts every processing run toward the retry cap, resumed runs included', function () {
    $media = $this->uploadedMedia(MediaFiles::jpeg(800, 600));

    ProcessMediaJob::dispatchSync($media->id);
    expect($media->refresh()->processing_attempts)->toBe(1);

    $resumed = $this->uploadedMedia(MediaFiles::jpeg(800, 600));
    $resumed->forceFill(['status' => MediaStatus::Processing, 'processing_attempts' => 1])->save();
    ProcessMediaJob::dispatchSync($resumed->id);
    expect($resumed->refresh()->processing_attempts)->toBe(2);
});

it('does not process a row the sweeper has claimed', function () {
    $media = $this->uploadedMedia(MediaFiles::jpeg(800, 600));
    $media->forceFill(['status' => MediaStatus::Deleting])->save();

    ProcessMediaJob::dispatchSync($media->id);

    expect($media->refresh()->status)->toBe(MediaStatus::Deleting)
        ->and($media->processing_attempts)->toBe(0);
});

describe('media:sweep-temp', function () {
    it('removes temp directories older than a reservation and keeps live ones', function () {
        $root = (string) config('media.processing.temp_dir');
        File::deleteDirectory($root);
        File::ensureDirectoryExists("{$root}/stale");
        File::ensureDirectoryExists("{$root}/live");
        touch("{$root}/stale", Date::now()->getTimestamp() - (int) config('queue.connections.media.retry_after') - 1);
        touch("{$root}/live", Date::now()->getTimestamp());

        $this->artisan('media:sweep-temp')->assertSuccessful();

        expect(File::isDirectory("{$root}/stale"))->toBeFalse()
            ->and(File::isDirectory("{$root}/live"))->toBeTrue();

        File::deleteDirectory($root);
    });
});

it('schedules the lifecycle commands per specs/20 §3', function () {
    $events = collect(app(Schedule::class)->events());
    $expected = [
        'media:sweep-orphans' => '20 * * * *',
        'media:retry-failed' => '45 */6 * * *',
        'media:purge-deleted' => '30 2 * * *',
        'media:reconcile-storage' => '0 5 * * 0',
    ];

    foreach ($expected as $command => $cron) {
        $event = $events->first(fn ($e) => str_contains((string) $e->command, $command));

        expect($event)->not->toBeNull()
            ->and($event->expression)->toBe($cron)
            ->and($event->withoutOverlapping)->toBeTrue()
            ->and($event->onOneServer)->toBeTrue()
            ->and($event->runInBackground)->toBeTrue();
    }
});

it('reads lifecycle windows from config', function () {
    config(['media.lifecycle.purge_after_days' => 1]);
    $media = Media::factory()->ready()->create();
    $media->delete();
    $this->travel(1)->days();

    expect(app(MediaLifecycleService::class)->purgeDeleted(dryRun: true))->toBe(1);
});
